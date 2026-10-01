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
}
