<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor\service;

use grade_item;
use moodle_url;

/**
 * Deterministic gradebook diagnostics.
 *
 * Findings describe observable configuration states. They do not assert that an unusual
 * configuration is necessarily wrong.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostic_service {
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
     * Run all deterministic checks.
     *
     * @return array{summary:array,findings:array}
     */
    public function diagnose(): array {
        global $DB;

        $categories = $DB->get_records('grade_categories', ['courseid' => $this->courseid], 'depth ASC, id ASC');
        $items = grade_item::fetch_all(['courseid' => $this->courseid]) ?: [];
        $findings = [];

        foreach ($categories as $categoryrecord) {
            $this->check_empty_category($categoryrecord, $findings);
            $this->check_category_rules($categoryrecord, $findings);
        }

        foreach ($items as $item) {
            $this->check_item($item, $findings);
        }

        $this->check_overrides_and_locked_grades($items, $findings);
        $this->check_hidden_relevant_items($items, $findings);
        $this->check_unusual_weight_concentration($categories, $findings);

        $counts = ['review' => 0, 'info' => 0];
        foreach ($findings as $finding) {
            $counts[$finding['attention']]++;
        }

        return [
            'summary' => [
                'categories' => count($categories),
                'items' => count($items),
                'findings' => count($findings),
                'review' => $counts['review'],
                'info' => $counts['info'],
            ],
            'findings' => $findings,
        ];
    }

    /**
     * Find categories that currently contain no direct item and no child category.
     *
     * @param object $category Category record.
     * @param array $findings Findings.
     * @return void
     */
    private function check_empty_category(object $category, array &$findings): void {
        global $DB;
        $childcategories = $DB->count_records('grade_categories', ['parent' => $category->id]);
        $params = ['courseid' => $this->courseid, 'categoryid' => $category->id];
        $childitems = $DB->count_records_select(
            'grade_items',
            'courseid = :courseid AND categoryid = :categoryid AND itemtype NOT IN (\'course\', \'category\')',
            $params
        );
        if ($childcategories === 0 && $childitems === 0 && !empty($category->parent)) {
            $this->add_finding($findings, [
                'code' => 'empty_category:' . $category->id,
                'type' => 'empty_category',
                'attention' => 'info',
                'label' => format_string($category->fullname),
                'evidence' => get_string('finding:emptycategory', 'local_gradebookdoctor'),
                'url' => $this->category_url((int)$category->id),
            ]);
        }
    }

    /**
     * Check category-level settings that deserve an explicit look.
     *
     * @param object $category Category record.
     * @param array $findings Findings.
     * @return void
     */
    private function check_category_rules(object $category, array &$findings): void {
        if ((int)$category->keephigh > 0 && (int)$category->droplow > 0) {
            $this->add_finding($findings, [
                'code' => 'keep_and_drop:' . $category->id,
                'type' => 'unusual_combination',
                'attention' => 'review',
                'label' => format_string($category->fullname),
                'evidence' => get_string('finding:keepanddrop', 'local_gradebookdoctor', [
                    'keep' => (int)$category->keephigh,
                    'drop' => (int)$category->droplow,
                ]),
                'url' => $this->category_url((int)$category->id),
            ]);
        }

        if ((int)$category->aggregation === GRADE_AGGREGATE_SUM) {
            $this->check_natural_manual_weights($category, $findings);
        }
    }

    /**
     * Check manual natural aggregation weights without pretending arbitrary weights are invalid.
     *
     * @param object $category Category record.
     * @param array $findings Findings.
     * @return void
     */
    private function check_natural_manual_weights(object $category, array &$findings): void {
        global $DB;
        $manual = $DB->get_records('grade_items', [
            'courseid' => $this->courseid,
            'categoryid' => $category->id,
            'weightoverride' => 1,
        ]);
        if (!$manual) {
            return;
        }

        $sum = 0.0;
        $zero = [];
        foreach ($manual as $item) {
            $sum += (float)$item->aggregationcoef2;
            if ((float)$item->aggregationcoef2 <= 0.0) {
                $zero[] = (int)$item->id;
            }
        }

        $this->add_finding($findings, [
            'code' => 'manual_weights:' . $category->id,
            'type' => 'manual_weights',
            'attention' => ($sum > 1.00001 || $zero) ? 'review' : 'info',
            'label' => format_string($category->fullname),
            'evidence' => get_string('finding:manualweights', 'local_gradebookdoctor', [
                'count' => count($manual),
                'sum' => format_float($sum * 100, 2) . '%',
                'zero' => $zero ? implode(', ', $zero) : get_string('none'),
            ]),
            'url' => $this->category_url((int)$category->id),
        ]);
    }

    /**
     * Check one grade item.
     *
     * @param grade_item $item Grade item.
     * @param array $findings Findings.
     * @return void
     */
    private function check_item(grade_item $item, array &$findings): void {
        global $DB;
        $label = $this->item_label($item);

        if (!empty($item->needsupdate)) {
            $this->add_finding($findings, [
                'code' => 'needs_update:' . $item->id,
                'type' => 'needs_update',
                'attention' => 'review',
                'label' => $label,
                'evidence' => get_string('finding:needsupdate', 'local_gradebookdoctor'),
                'url' => $this->item_url($item),
            ]);
        }

        if (!empty($item->locked)) {
            $this->add_finding($findings, [
                'code' => 'item_locked:' . $item->id,
                'type' => 'locked_item',
                'attention' => 'info',
                'label' => $label,
                'evidence' => get_string('finding:itemlocked', 'local_gradebookdoctor'),
                'url' => $this->item_url($item),
            ]);
        }

        if ((float)$item->multfactor !== 1.0 || (float)$item->plusfactor !== 0.0) {
            $this->add_finding($findings, [
                'code' => 'grade_adjustment:' . $item->id,
                'type' => 'grade_adjustment',
                'attention' => 'review',
                'label' => $label,
                'evidence' => get_string('finding:adjustment', 'local_gradebookdoctor', [
                    'mult' => format_float((float)$item->multfactor, 5),
                    'plus' => format_float((float)$item->plusfactor, 5),
                ]),
                'url' => $this->item_url($item),
            ]);
        }

        if ((float)$item->grademax <= (float)$item->grademin && (int)$item->gradetype === GRADE_TYPE_VALUE) {
            $this->add_finding($findings, [
                'code' => 'invalid_range:' . $item->id,
                'type' => 'grade_range',
                'attention' => 'review',
                'label' => $label,
                'evidence' => get_string('finding:invalidrange', 'local_gradebookdoctor', [
                    'min' => $item->grademin,
                    'max' => $item->grademax,
                ]),
                'url' => $this->item_url($item),
            ]);
        }

        if (!empty($item->calculation)) {
            $this->check_formula($item, $findings);
        }

        if ($item->itemtype !== 'course' && $item->itemtype !== 'category') {
            $hasgrades = $DB->record_exists_select(
                'grade_grades',
                'itemid = :itemid AND finalgrade IS NOT NULL',
                ['itemid' => $item->id]
            );
            if (!$hasgrades) {
                $this->add_finding($findings, [
                    'code' => 'no_final_grades:' . $item->id,
                    'type' => 'unused_or_ungraded',
                    'attention' => 'info',
                    'label' => $label,
                    'evidence' => get_string('finding:nofinalgrades', 'local_gradebookdoctor'),
                    'url' => $this->item_url($item),
                ]);
            }
        }
    }

    /**
     * Static checks for obvious formula problems.
     *
     * @param grade_item $item Grade item.
     * @param array $findings Findings.
     * @return void
     */
    private function check_formula(grade_item $item, array &$findings): void {
        $raw = (string)$item->calculation;
        $readable = grade_item::denormalize_formula($raw, $this->courseid);
        $validation = $item->validate_formula($readable);
        if ($validation !== true) {
            $detail = is_string($validation) && $validation !== ''
                ? $validation
                : get_string('unknownerror');
            $this->add_finding($findings, [
                'code' => 'formula_invalid:' . $item->id,
                'type' => 'formula_invalid',
                'attention' => 'review',
                'label' => $this->item_label($item),
                'evidence' => get_string('finding:formulainvalid', 'local_gradebookdoctor', $detail),
                'url' => $this->item_url($item),
            ]);
        }

        preg_match_all('/##gi(\d+)##/', $raw, $matches);
        $ids = array_values(array_unique(array_map('intval', $matches[1] ?? [])));

        if (in_array((int)$item->id, $ids, true)) {
            $this->add_finding($findings, [
                'code' => 'formula_self_reference:' . $item->id,
                'type' => 'formula_reference',
                'attention' => 'review',
                'label' => $this->item_label($item),
                'evidence' => get_string('finding:formulaselfreference', 'local_gradebookdoctor'),
                'url' => $this->item_url($item),
            ]);
        }

        foreach ($ids as $dependencyid) {
            $dependency = grade_item::fetch(['id' => $dependencyid, 'courseid' => $this->courseid]);
            if (!$dependency) {
                $this->add_finding($findings, [
                    'code' => 'formula_missing_reference:' . $item->id . ':' . $dependencyid,
                    'type' => 'formula_reference',
                    'attention' => 'review',
                    'label' => $this->item_label($item),
                    'evidence' => get_string('finding:formulamissingreference', 'local_gradebookdoctor', $dependencyid),
                    'url' => $this->item_url($item),
                ]);
            }
        }

        if (str_contains($raw, '[[') || str_contains($raw, ']]')) {
            $this->add_finding($findings, [
                'code' => 'formula_unresolved_reference:' . $item->id,
                'type' => 'formula_reference',
                'attention' => 'review',
                'label' => $this->item_label($item),
                'evidence' => get_string('finding:formulaunresolved', 'local_gradebookdoctor'),
                'url' => $this->item_url($item),
            ]);
        }
    }

    /**
     * Summarise manual overrides and per-user locks without sending identities to AI.
     *
     * @param grade_item[] $items Items.
     * @param array $findings Findings.
     * @return void
     */
    private function check_overrides_and_locked_grades(array $items, array &$findings): void {
        global $DB;
        foreach ($items as $item) {
            $overrides = $DB->count_records_select('grade_grades', 'itemid = :itemid AND overridden > 0', ['itemid' => $item->id]);
            if ($overrides > 0) {
                $this->add_finding($findings, [
                    'code' => 'overrides:' . $item->id,
                    'type' => 'overrides',
                    'attention' => 'review',
                    'label' => $this->item_label($item),
                    'evidence' => get_string('finding:overrides', 'local_gradebookdoctor', $overrides),
                    'url' => $this->item_url($item),
                ]);
            }

            $locked = $DB->count_records_select('grade_grades', 'itemid = :itemid AND locked > 0', ['itemid' => $item->id]);
            if ($locked > 0) {
                $this->add_finding($findings, [
                    'code' => 'locked_grades:' . $item->id,
                    'type' => 'locked_grades',
                    'attention' => 'info',
                    'label' => $this->item_label($item),
                    'evidence' => get_string('finding:lockedgrades', 'local_gradebookdoctor', $locked),
                    'url' => $this->item_url($item),
                ]);
            }
        }
    }

    /**
     * Hidden items can still participate in aggregation; flag only when core metadata proves that they do.
     *
     * @param grade_item[] $items Items.
     * @param array $findings Findings.
     * @return void
     */
    private function check_hidden_relevant_items(array $items, array &$findings): void {
        global $DB;
        foreach ($items as $item) {
            if (empty($item->hidden)) {
                continue;
            }
            $used = $DB->count_records_select(
                'grade_grades',
                'itemid = :itemid AND aggregationstatus IN (:used, :extra)',
                ['itemid' => $item->id, 'used' => 'used', 'extra' => 'extra']
            );
            if ($used > 0) {
                $this->add_finding($findings, [
                    'code' => 'hidden_but_used:' . $item->id,
                    'type' => 'hidden_relevant',
                    'attention' => 'review',
                    'label' => $this->item_label($item),
                    'evidence' => get_string('finding:hiddenbutused', 'local_gradebookdoctor', $used),
                    'url' => $this->item_url($item),
                ]);
            }
        }
    }

    /**
     * Identify a very concentrated effective weight in categories with at least three contributors.
     * This uses Moodle's own stored aggregationweight instead of reconstructing weights.
     *
     * @param array $categories Category records.
     * @param array $findings Findings.
     * @return void
     */
    private function check_unusual_weight_concentration(array $categories, array &$findings): void {
        global $DB;
        foreach ($categories as $categoryrecord) {
            if (!in_array((int)$categoryrecord->aggregation, [GRADE_AGGREGATE_WEIGHTED_MEAN, GRADE_AGGREGATE_SUM], true)) {
                continue;
            }

            $directids = $this->direct_child_itemids((int)$categoryrecord->id);
            if (count($directids) < 3) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($directids, SQL_PARAMS_NAMED, 'gi');
            $params['used'] = 'used';
            $sql = "SELECT itemid, MAX(aggregationweight) AS maxweight
                      FROM {grade_grades}
                     WHERE itemid {$insql}
                       AND aggregationstatus = :used
                       AND aggregationweight IS NOT NULL
                  GROUP BY itemid";
            $weights = $DB->get_records_sql($sql, $params);
            $maxweight = 0.0;
            $maxitemid = 0;
            foreach ($weights as $record) {
                if ((float)$record->maxweight > $maxweight) {
                    $maxweight = (float)$record->maxweight;
                    $maxitemid = (int)$record->itemid;
                }
            }
            if ($maxweight >= 0.90 && $maxitemid) {
                $item = grade_item::fetch(['id' => $maxitemid, 'courseid' => $this->courseid]);
                $this->add_finding($findings, [
                    'code' => 'weight_concentration:' . $categoryrecord->id,
                    'type' => 'weight_concentration',
                    'attention' => 'review',
                    'label' => format_string($categoryrecord->fullname),
                    'evidence' => get_string('finding:weightconcentration', 'local_gradebookdoctor', [
                        'item' => $item ? $this->item_label($item) : '#' . $maxitemid,
                        'weight' => format_float($maxweight * 100, 2) . '%',
                    ]),
                    'url' => $this->category_url((int)$categoryrecord->id),
                ]);
            }
        }
    }

    /**
     * Read direct child item ids without invoking a core hierarchy helper that may repair paths.
     *
     * @param int $categoryid Category id.
     * @return int[]
     */
    private function direct_child_itemids(int $categoryid): array {
        global $DB;

        $params = ['courseid' => $this->courseid, 'categoryid' => $categoryid];
        $ids = $DB->get_fieldset_select(
            'grade_items',
            'id',
            "courseid = :courseid AND categoryid = :categoryid AND itemtype NOT IN ('course', 'category')",
            $params
        );
        $ids = array_map('intval', $ids);

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
                $ids[] = (int)$item->id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Add a normalised finding.
     *
     * @param array $findings Findings.
     * @param array $finding Finding.
     * @return void
     */
    private function add_finding(array &$findings, array $finding): void {
        $finding['attentionclass'] = $finding['attention'] === 'review'
            ? 'badge bg-warning text-dark'
            : 'badge bg-info text-dark';
        $finding['attentionlabel'] = get_string('attention:' . $finding['attention'], 'local_gradebookdoctor');
        $findings[] = $finding;
    }

    /**
     * Label grade item.
     *
     * @param grade_item $item Item.
     * @return string
     */
    private function item_label(grade_item $item): string {
        global $DB;
        if ($item->itemtype === 'course') {
            return get_string('coursetotal', 'grades');
        }
        if ($item->itemtype === 'category') {
            $name = $DB->get_field('grade_categories', 'fullname', ['id' => $item->iteminstance]);
            return $name ? format_string($name) : get_string('categorytotal', 'grades');
        }
        return !empty($item->itemname)
            ? format_string($item->itemname)
            : get_string('unnameditem', 'local_gradebookdoctor', $item->id);
    }

    /**
     * Item configuration URL.
     *
     * @param grade_item $item Item.
     * @return string
     */
    private function item_url(grade_item $item): string {
        if ($item->itemtype === 'course') {
            return (new moodle_url('/grade/edit/tree/index.php', ['id' => $this->courseid]))->out(false);
        }
        if ($item->itemtype === 'category') {
            return $this->category_url((int)$item->iteminstance);
        }
        return (new moodle_url('/grade/edit/tree/item.php', [
            'courseid' => $this->courseid,
            'id' => $item->id,
        ]))->out(false);
    }

    /**
     * Category configuration URL.
     *
     * @param int $categoryid Category id.
     * @return string
     */
    private function category_url(int $categoryid): string {
        return (new moodle_url('/grade/edit/tree/category.php', [
            'courseid' => $this->courseid,
            'id' => $categoryid,
        ]))->out(false);
    }
}
