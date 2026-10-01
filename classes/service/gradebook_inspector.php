<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_gradebookdoctor\service;

use context_course;
use grade_category;
use grade_item;
use moodle_exception;
use moodle_url;

/**
 * Read-only inspector for Moodle gradebook calculations.
 *
 * This class deliberately consumes final grades and aggregation metadata produced by Moodle.
 * It does not implement a second grade calculator.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook_inspector {
    /** Maximum nested levels accepted in an explanation tree. */
    private const MAX_DEPTH = 20;

    /** Maximum nodes included in one report. */
    private const MAX_NODES = 500;

    /** @var int Number of nodes generated for the current request. */
    private int $nodecount = 0;

    /**
     * Constructor.
     *
     * @param int $courseid Course id.
     */
    public function __construct(private readonly int $courseid) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Return grade items that may be explained.
     *
     * @return grade_item[]
     */
    public function get_items(): array {
        $items = grade_item::fetch_all(['courseid' => $this->courseid]);
        if (!$items) {
            return [];
        }
        uasort($items, static fn(grade_item $a, grade_item $b): int => $a->sortorder <=> $b->sortorder);
        return $items;
    }

    /**
     * Build a deterministic explanation tree for one grade item and user.
     *
     * @param int $userid User id.
     * @param int $itemid Grade item id.
     * @return array
     */
    public function explain(int $userid, int $itemid): array {
        $this->nodecount = 0;
        $item = grade_item::fetch(['id' => $itemid, 'courseid' => $this->courseid]);
        if (!$item) {
            throw new moodle_exception('error:invalidgradeitem', 'local_gradebookdoctor');
        }

        return $this->build_item_node($item, $userid, 0, []);
    }

    /**
     * Compare two users against the same grade item using two independent Moodle snapshots.
     *
     * @param int $userida User A.
     * @param int $useridb User B.
     * @param int $itemid Grade item.
     * @return array
     */
    public function compare(int $userida, int $useridb, int $itemid): array {
        $left = $this->explain($userida, $itemid);
        $right = $this->explain($useridb, $itemid);
        $leftmap = $this->flatten_by_itemid($left);
        $rightmap = $this->flatten_by_itemid($right);
        $ids = array_unique(array_merge(array_keys($leftmap), array_keys($rightmap)));
        sort($ids, SORT_NUMERIC);

        $differences = [];
        foreach ($ids as $id) {
            $a = $leftmap[$id] ?? null;
            $b = $rightmap[$id] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            if ($this->same_comparison_state($a, $b)) {
                continue;
            }
            $differences[] = [
                'itemid' => $id,
                'label' => $a['label'],
                'a' => [
                    'finalgrade' => $a['finalgrade'],
                    'aggregationstatus' => $a['aggregationstatus'],
                    'aggregationweight' => $a['aggregationweight'],
                    'excluded' => $a['excluded'],
                    'overridden' => $a['overridden'],
                    'locked' => $a['locked'],
                ],
                'b' => [
                    'finalgrade' => $b['finalgrade'],
                    'aggregationstatus' => $b['aggregationstatus'],
                    'aggregationweight' => $b['aggregationweight'],
                    'excluded' => $b['excluded'],
                    'overridden' => $b['overridden'],
                    'locked' => $b['locked'],
                ],
            ];
        }

        return [
            'itemid' => $itemid,
            'student_a' => $left,
            'student_b' => $right,
            'differences' => $differences,
        ];
    }

    /**
     * Convert a tree to presentation rows.
     *
     * @param array $tree Tree.
     * @return array
     */
    public function flatten_for_display(array $tree): array {
        $rows = [];
        $walk = function (array $node, int $depth) use (&$walk, &$rows): void {
            $status = (string)($node['aggregationstatus'] ?? '');
            $rows[] = [
                'itemid' => $node['itemid'],
                'label' => $node['label'],
                'itemtype' => $node['itemtype'],
                'itemmodule' => $node['itemmodule'] ?: '—',
                'depth' => $depth,
                'indentrem' => min(12, $depth * 1.25),
                'finalgrade' => $this->format_nullable_number($node['finalgrade']),
                'rawgrade' => $this->format_nullable_number($node['rawgrade']),
                'range' => $this->format_nullable_number($node['grademin']) . '–' . $this->format_nullable_number($node['grademax']),
                'aggregation' => $node['aggregation'] ?: '—',
                'aggregationstatus' => $status ?: get_string('status:notrecorded', 'local_gradebookdoctor'),
                'statusclass' => $this->status_class($status),
                'weight' => $node['weightpercent'] === null ? '—' : format_float($node['weightpercent'], 2) . '%',
                'formula' => $node['formula'] ?: '',
                'hasformula' => !empty($node['formula']),
                'hidden' => $node['hidden'],
                'excluded' => $node['excluded'],
                'overridden' => $node['overridden'],
                'locked' => $node['locked'],
                'needsupdate' => $node['needsupdate'],
                'extracredit' => $node['extracredit'],
                'editurl' => $node['editurl'],
            ];
            foreach ($node['children'] as $child) {
                $walk($child, $depth + 1);
            }
            foreach ($node['dependencies'] as $dependency) {
                $copy = $dependency;
                $copy['label'] = get_string('formula:dependency', 'local_gradebookdoctor', $copy['label']);
                $walk($copy, $depth + 1);
            }
        };
        $walk($tree, 0);
        return $rows;
    }

    /**
     * Build a node for one grade item.
     *
     * @param grade_item $item Grade item.
     * @param int $userid User id.
     * @param int $depth Current depth.
     * @param array $visited Grade item ids already visited through formulas.
     * @return array
     */
    private function build_item_node(grade_item $item, int $userid, int $depth, array $visited): array {
        global $DB;

        $this->nodecount++;
        if ($depth > self::MAX_DEPTH || $this->nodecount > self::MAX_NODES) {
            return $this->truncated_node($item);
        }

        $graderecord = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
        $category = false;
        if ($item->itemtype === 'course' || $item->itemtype === 'category') {
            $category = grade_category::fetch(['id' => $item->iteminstance, 'courseid' => $this->courseid]);
        }

        $formula = $this->get_formula($item);
        $parentcategory = $this->aggregation_parent_category($item);
        $extracredit = false;
        if ($parentcategory instanceof grade_category &&
            grade_category::aggregation_uses_extracredit($parentcategory->aggregation)) {
            $extracredit = (float)$item->aggregationcoef > 0.0;
        }

        $status = $graderecord ? (string)($graderecord->aggregationstatus ?? '') : '';
        $weight = $graderecord && $graderecord->aggregationweight !== null
            ? (float)$graderecord->aggregationweight
            : null;

        $node = [
            'itemid' => (int)$item->id,
            'label' => $this->item_label($item),
            'itemtype' => (string)$item->itemtype,
            'itemmodule' => (string)($item->itemmodule ?? ''),
            'grademin' => (float)$item->grademin,
            'grademax' => (float)$item->grademax,
            'rawgrade' => $graderecord && $graderecord->rawgrade !== null ? (float)$graderecord->rawgrade : null,
            'finalgrade' => $graderecord && $graderecord->finalgrade !== null ? (float)$graderecord->finalgrade : null,
            'aggregation' => $category ? $this->aggregation_name((int)$category->aggregation) : null,
            'aggregationcode' => $category ? (int)$category->aggregation : null,
            'aggregationstatus' => $status,
            'aggregationweight' => $weight,
            'weightpercent' => $weight === null ? null : $weight * 100,
            'formula' => $formula,
            'excluded' => $graderecord ? !empty($graderecord->excluded) : false,
            'overridden' => $graderecord ? !empty($graderecord->overridden) : false,
            'locked' => ($graderecord ? !empty($graderecord->locked) : false) || !empty($item->locked),
            'hidden' => !empty($item->hidden),
            'needsupdate' => !empty($item->needsupdate),
            'extracredit' => $extracredit,
            'keephigh' => $category ? (int)$category->keephigh : 0,
            'droplow' => $category ? (int)$category->droplow : 0,
            'aggregateonlygraded' => $category ? !empty($category->aggregateonlygraded) : null,
            'children' => [],
            'dependencies' => [],
            'editurl' => $this->edit_url($item),
            'truncated' => false,
        ];

        if ($category) {
            $node['children'] = $this->build_category_children((int)$category->id, $userid, $depth + 1, $visited);
        }

        if ($formula && !in_array((int)$item->id, $visited, true)) {
            $nextvisited = array_merge($visited, [(int)$item->id]);
            foreach ($this->formula_dependency_ids((string)$item->calculation) as $dependencyid) {
                if (in_array($dependencyid, $nextvisited, true)) {
                    continue;
                }
                $dependency = grade_item::fetch(['id' => $dependencyid, 'courseid' => $this->courseid]);
                if ($dependency) {
                    $node['dependencies'][] = $this->build_item_node($dependency, $userid, $depth + 1, $nextvisited);
                }
            }
        }

        return $node;
    }

    /**
     * Build direct category children without calling grade_category::get_children().
     *
     * The core helper can repair inconsistent category paths as a side effect. Gradebook Doctor
     * is intentionally read-only, so the hierarchy is assembled from records instead.
     *
     * @param int $categoryid Category id.
     * @param int $userid User id.
     * @param int $depth Depth.
     * @param array $visited Formula recursion guard.
     * @return array
     */
    private function build_category_children(int $categoryid, int $userid, int $depth, array $visited): array {
        global $DB;

        $entries = [];
        $params = ['courseid' => $this->courseid, 'categoryid' => $categoryid];
        $records = $DB->get_records_select(
            'grade_items',
            "courseid = :courseid AND categoryid = :categoryid AND itemtype NOT IN ('course', 'category')",
            $params,
            'sortorder ASC, id ASC'
        );
        foreach ($records as $record) {
            $item = new grade_item($record, false);
            $entries[] = ['sortorder' => (int)$item->sortorder, 'item' => $item];
        }

        $childcategories = $DB->get_records('grade_categories', [
            'courseid' => $this->courseid,
            'parent' => $categoryid,
        ]);
        foreach ($childcategories as $childcategory) {
            $item = grade_item::fetch([
                'courseid' => $this->courseid,
                'itemtype' => 'category',
                'iteminstance' => $childcategory->id,
            ]);
            if ($item) {
                $entries[] = ['sortorder' => (int)$item->sortorder, 'item' => $item];
            }
        }

        usort($entries, static function (array $a, array $b): int {
            $sort = $a['sortorder'] <=> $b['sortorder'];
            if ($sort !== 0) {
                return $sort;
            }
            return $a['item']->id <=> $b['item']->id;
        });

        $children = [];
        foreach ($entries as $entry) {
            $children[] = $this->build_item_node($entry['item'], $userid, $depth, $visited);
        }
        return $children;
    }

    /**
     * Return the category in which this item contributes to aggregation.
     *
     * grade_item::get_parent_category() returns the represented category for category totals,
     * not the category that aggregates that total, so category items need special handling.
     *
     * @param grade_item $item Grade item.
     * @return grade_category|false
     */
    private function aggregation_parent_category(grade_item $item): grade_category|false {
        if ($item->itemtype === 'course') {
            return false;
        }
        if ($item->itemtype === 'category') {
            $represented = grade_category::fetch([
                'id' => $item->iteminstance,
                'courseid' => $this->courseid,
            ]);
            if (!$represented || empty($represented->parent)) {
                return false;
            }
            return grade_category::fetch([
                'id' => $represented->parent,
                'courseid' => $this->courseid,
            ]);
        }
        if (empty($item->categoryid)) {
            return false;
        }
        return grade_category::fetch([
            'id' => $item->categoryid,
            'courseid' => $this->courseid,
        ]);
    }

    /**
     * Convert raw formula to a readable idnumber form when possible.
     *
     * @param grade_item $item Grade item.
     * @return string|null
     */
    private function get_formula(grade_item $item): ?string {
        if (empty($item->calculation)) {
            return null;
        }
        return grade_item::denormalize_formula((string)$item->calculation, $this->courseid);
    }

    /**
     * Extract normalized grade item references from a stored formula.
     *
     * @param string $formula Raw stored formula.
     * @return int[]
     */
    private function formula_dependency_ids(string $formula): array {
        if (!preg_match_all('/##gi(\d+)##/', $formula, $matches)) {
            return [];
        }
        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    /**
     * Human label for one item.
     *
     * @param grade_item $item Grade item.
     * @return string
     */
    public function item_label(grade_item $item): string {
        global $DB;
        if ($item->itemtype === 'course') {
            return get_string('coursetotal', 'grades');
        }
        if ($item->itemtype === 'category') {
            $name = $DB->get_field('grade_categories', 'fullname', ['id' => $item->iteminstance]);
            return $name ? (string)$name : get_string('categorytotal', 'grades');
        }
        if (!empty($item->itemname)) {
            return format_string((string)$item->itemname, true, ['context' => context_course::instance($this->courseid)]);
        }
        return get_string('unnameditem', 'local_gradebookdoctor', $item->id);
    }

    /**
     * Gradebook configuration URL for the represented item/category.
     *
     * @param grade_item $item Grade item.
     * @return string
     */
    private function edit_url(grade_item $item): string {
        if ($item->itemtype === 'category') {
            return (new moodle_url('/grade/edit/tree/category.php', [
                'courseid' => $this->courseid,
                'id' => $item->iteminstance,
            ]))->out(false);
        }
        if ($item->itemtype === 'course') {
            return (new moodle_url('/grade/edit/tree/index.php', ['id' => $this->courseid]))->out(false);
        }
        return (new moodle_url('/grade/edit/tree/item.php', [
            'courseid' => $this->courseid,
            'id' => $item->id,
        ]))->out(false);
    }

    /**
     * Friendly aggregation name.
     *
     * @param int $aggregation Aggregation constant.
     * @return string
     */
    private function aggregation_name(int $aggregation): string {
        $map = [
            GRADE_AGGREGATE_MEAN => 'mean',
            GRADE_AGGREGATE_WEIGHTED_MEAN => 'weightedmean',
            GRADE_AGGREGATE_WEIGHTED_MEAN2 => 'simpleweightedmean',
            GRADE_AGGREGATE_EXTRACREDIT_MEAN => 'extracreditmean',
            GRADE_AGGREGATE_MEDIAN => 'median',
            GRADE_AGGREGATE_MIN => 'min',
            GRADE_AGGREGATE_MAX => 'max',
            GRADE_AGGREGATE_MODE => 'mode',
            GRADE_AGGREGATE_SUM => 'natural',
        ];
        $key = $map[$aggregation] ?? 'unknown';
        return get_string('aggregation:' . $key, 'local_gradebookdoctor');
    }

    /**
     * Flatten tree by item id for comparison.
     *
     * @param array $tree Tree.
     * @return array
     */
    private function flatten_by_itemid(array $tree): array {
        $result = [];
        $walk = function (array $node) use (&$walk, &$result): void {
            if (!isset($result[$node['itemid']])) {
                $result[$node['itemid']] = $node;
            }
            foreach ($node['children'] as $child) {
                $walk($child);
            }
            foreach ($node['dependencies'] as $child) {
                $walk($child);
            }
        };
        $walk($tree);
        return $result;
    }

    /**
     * Compare only gradebook facts relevant to a difference report.
     *
     * @param array $a A state.
     * @param array $b B state.
     * @return bool
     */
    private function same_comparison_state(array $a, array $b): bool {
        foreach (['finalgrade', 'aggregationstatus', 'aggregationweight', 'excluded', 'overridden', 'locked'] as $field) {
            if (($a[$field] ?? null) !== ($b[$field] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Bootstrap badge class for a core aggregation status.
     *
     * @param string $status Core aggregation status.
     * @return string
     */
    private function status_class(string $status): string {
        return match ($status) {
            'used' => 'badge bg-success',
            'extra' => 'badge bg-info text-dark',
            'excluded', 'dropped' => 'badge bg-warning text-dark',
            'novalue' => 'badge bg-secondary',
            default => 'badge bg-light text-dark border',
        };
    }

    /**
     * Number formatter preserving null.
     *
     * @param float|int|null $number Number.
     * @return string
     */
    private function format_nullable_number(float|int|null $number): string {
        return $number === null ? '—' : format_float($number, 5, true, true);
    }

    /**
     * Minimal node when a pathological tree exceeds safeguards.
     *
     * @param grade_item $item Grade item.
     * @return array
     */
    private function truncated_node(grade_item $item): array {
        return [
            'itemid' => (int)$item->id,
            'label' => $this->item_label($item),
            'itemtype' => (string)$item->itemtype,
            'itemmodule' => (string)($item->itemmodule ?? ''),
            'grademin' => (float)$item->grademin,
            'grademax' => (float)$item->grademax,
            'rawgrade' => null,
            'finalgrade' => null,
            'aggregation' => null,
            'aggregationcode' => null,
            'aggregationstatus' => '',
            'aggregationweight' => null,
            'weightpercent' => null,
            'formula' => null,
            'excluded' => false,
            'overridden' => false,
            'locked' => false,
            'hidden' => false,
            'needsupdate' => false,
            'extracredit' => false,
            'keephigh' => 0,
            'droplow' => 0,
            'aggregateonlygraded' => null,
            'children' => [],
            'dependencies' => [],
            'editurl' => $this->edit_url($item),
            'truncated' => true,
        ];
    }
}
