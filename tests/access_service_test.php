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

namespace local_gradebookdoctor;

use advanced_testcase;
use context_course;
use local_gradebookdoctor\service\access_service;
use required_capability_exception;

/**
 * Access tests.
 *
 * @package local_gradebookdoctor
 * @covers \local_gradebookdoctor\service\access_service
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_service_test extends advanced_testcase {
    /**
     * Method test_authorised_grader_can_access.
     *
     * @return void Return value.
     */
    public function test_authorised_grader_can_access(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        access_service::require_course_access(context_course::instance($course->id));
        $this->assertTrue(true);
    }

    /**
     * Method test_student_cannot_access_gradebook_doctor.
     *
     * @return void Return value.
     */
    public function test_student_cannot_access_gradebook_doctor(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        access_service::require_course_access(context_course::instance($course->id));
    }

    /**
     * Separate groups restrict both the student selector and direct requests.
     *
     * @return void
     */
    public function test_restricted_grader_cannot_inspect_other_groups(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();
        $ungrouped = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        foreach ([$studenta, $studentb, $ungrouped] as $student) {
            $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        }
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        $context = context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);
        $this->assertSame(SEPARATEGROUPS, (int) groups_get_course_groupmode(get_course($course->id)));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context));

        $users = access_service::get_course_users($course->id);
        $this->assertArrayHasKey($studenta->id, $users);
        $this->assertArrayNotHasKey($studentb->id, $users);
        $this->assertArrayNotHasKey($ungrouped->id, $users);
        access_service::require_course_user($course->id, $studenta->id);

        $this->expectException(\moodle_exception::class);
        access_service::require_course_user($course->id, $studentb->id);
    }

    /**
     * A restricted grader with no group must not see any students.
     *
     * @return void
     */
    public function test_restricted_grader_without_group_sees_no_students(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $context = context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);
        $this->assertSame(SEPARATEGROUPS, (int) groups_get_course_groupmode(get_course($course->id)));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context));

        $this->assertSame([], access_service::get_course_users($course->id));
        $this->expectException(\moodle_exception::class);
        access_service::require_course_user($course->id, $student->id);
    }

    /**
     * Graders with accessallgroups retain full access in separate groups courses.
     *
     * @return void
     */
    public function test_accessallgroups_can_inspect_students_from_other_groups(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        $this->assertArrayHasKey($student->id, access_service::get_course_users($course->id));
        access_service::require_course_user($course->id, $student->id);
    }
}
