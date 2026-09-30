<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor;

use grade_category;
use grade_grade;
use grade_item;
use local_gradebookdoctor\service\gradebook_inspector;

/**
 * Gradebook snapshot tests.
 *
 * These tests let Moodle calculate grades, then assert that Gradebook Doctor reports
 * Moodle's stored result and metadata rather than calculating a competing value.
 *
 * @package local_gradebookdoctor
 * @covers \local_gradebookdoctor\service\gradebook_inspector
 */
final class gradebook_inspector_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $user;

    /** @var grade_category */
    private grade_category $rootcategory;

    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
        $this->rootcategory = grade_category::fetch_course_category($this->course->id);
    }

    public function test_weighted_mean_uses_moodle_final_grade_and_weight_metadata(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
        $this->rootcategory->update();
        $a = $this->create_item('A', 100, 80, 1.0);
        $b = $this->create_item('B', 100, 40, 3.0);
        grade_regrade_final_grades($this->course->id);

        $tree = $this->inspect_course_total();
        $this->assertEqualsWithDelta(50.0, (float)$tree['finalgrade'], 0.0001);
        $this->assertSame('Weighted mean of grades', $tree['aggregation']);
        $children = $this->children_by_id($tree);
        $this->assertEqualsWithDelta(25.0, (float)$children[$a->id]['weightpercent'], 0.01);
        $this->assertEqualsWithDelta(75.0, (float)$children[$b->id]['weightpercent'], 0.01);
    }

    public function test_simple_weighted_mean(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN2;
        $this->rootcategory->update();
        $this->create_item('A', 100, 80);
        $this->create_item('B', 50, 25);
        grade_regrade_final_grades($this->course->id);

        $tree = $this->inspect_course_total();
        $this->assertEqualsWithDelta(70.0, (float)$tree['finalgrade'], 0.0001);
        $this->assertSame('Simple weighted mean of grades', $tree['aggregation']);
    }

    public function test_natural_aggregation_reports_core_result(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_SUM;
        $this->rootcategory->update();
        $this->create_item('A', 100, 80);
        $this->create_item('B', 50, 25);
        grade_regrade_final_grades($this->course->id);

        $tree = $this->inspect_course_total();
        $this->assertSame('Natural', $tree['aggregation']);
        $this->assertNotNull($tree['finalgrade']);
        $this->assertCount(2, $tree['children']);
    }

    public function test_excluded_grade_is_reported_from_core_status(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_MEAN;
        $this->rootcategory->update();
        $a = $this->create_item('A', 100, 80);
        $this->create_item('B', 100, 40);
        $grade = grade_grade::fetch(['itemid' => $a->id, 'userid' => $this->user->id]);
        $grade->set_excluded(true);
        grade_regrade_final_grades($this->course->id);

        $children = $this->children_by_id($this->inspect_course_total());
        $this->assertTrue($children[$a->id]['excluded']);
        $this->assertSame('excluded', $children[$a->id]['aggregationstatus']);
    }

    public function test_override_is_reported_without_recalculation(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_MEAN;
        $this->rootcategory->update();
        $this->create_item('A', 100, 80);
        $this->create_item('B', 100, 40);
        grade_regrade_final_grades($this->course->id);

        $courseitem = $this->rootcategory->get_grade_item();
        $grade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $this->user->id]);
        $grade->finalgrade = 91.0;
        $grade->overridden = time();
        $grade->update('manual');

        $tree = (new gradebook_inspector($this->course->id))->explain($this->user->id, $courseitem->id);
        $this->assertTrue($tree['overridden']);
        $this->assertEqualsWithDelta(91.0, (float)$tree['finalgrade'], 0.0001);
    }

    public function test_hidden_and_locked_flags_are_reported(): void {
        $item = $this->create_item('Hidden', 100, 70);
        $item->hidden = 1;
        $item->locked = time();
        $item->update();
        grade_regrade_final_grades($this->course->id);

        $tree = (new gradebook_inspector($this->course->id))->explain($this->user->id, $item->id);
        $this->assertTrue($tree['hidden']);
        $this->assertTrue($tree['locked']);
    }

    public function test_formula_dependencies_are_exposed_but_value_remains_moodles(): void {
        $a = $this->create_item('A', 100, 80);
        $b = $this->create_item('B', 100, 40);
        $calc = $this->create_item('Calculated', 200, 120);
        $calc->calculation = '=##gi' . $a->id . '##+##gi' . $b->id . '##';
        $calc->update();

        $tree = (new gradebook_inspector($this->course->id))->explain($this->user->id, $calc->id);
        $ids = array_column($tree['dependencies'], 'itemid');
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertEqualsWithDelta(120.0, (float)$tree['finalgrade'], 0.0001);
    }

    public function test_extra_credit_is_reported_for_simple_weighted_mean(): void {
        $this->rootcategory->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN2;
        $this->rootcategory->update();
        $this->create_item('Normal', 100, 80);
        $extra = $this->create_item('Bonus', 10, 10);
        $extra->aggregationcoef = 1;
        $extra->update();
        grade_regrade_final_grades($this->course->id);

        $children = $this->children_by_id($this->inspect_course_total());
        $this->assertTrue($children[$extra->id]['extracredit']);
    }

    /**
     * Create one manual grade item and one final grade record.
     *
     * @param string $name Name.
     * @param float $max Max grade.
     * @param float $grade User grade.
     * @param float $coefficient Aggregation coefficient.
     * @return grade_item
     */
    private function create_item(string $name, float $max, float $grade, float $coefficient = 0.0): grade_item {
        $item = new grade_item([
            'courseid' => $this->course->id,
            'categoryid' => $this->rootcategory->id,
            'itemname' => $name,
            'itemtype' => 'manual',
            'gradetype' => GRADE_TYPE_VALUE,
            'grademin' => 0,
            'grademax' => $max,
            'aggregationcoef' => $coefficient,
        ], false);
        $item->insert('test');

        $gg = new grade_grade([
            'itemid' => $item->id,
            'userid' => $this->user->id,
            'rawgrade' => $grade,
            'rawgrademin' => 0,
            'rawgrademax' => $max,
            'finalgrade' => $grade,
        ], false);
        $gg->insert('test');
        return $item;
    }

    /**
     * Inspect the course total.
     *
     * @return array
     */
    private function inspect_course_total(): array {
        $courseitem = $this->rootcategory->get_grade_item();
        return (new gradebook_inspector($this->course->id))->explain($this->user->id, $courseitem->id);
    }

    /**
     * Index direct children by item id.
     *
     * @param array $tree Tree.
     * @return array
     */
    private function children_by_id(array $tree): array {
        $result = [];
        foreach ($tree['children'] as $child) {
            $result[$child['itemid']] = $child;
        }
        return $result;
    }
}
