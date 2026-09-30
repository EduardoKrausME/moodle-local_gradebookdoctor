<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor;

use context_course;
use local_gradebookdoctor\service\access_service;

/**
 * Access tests.
 *
 * @package local_gradebookdoctor
 * @covers \local_gradebookdoctor\service\access_service
 */
final class access_service_test extends \advanced_testcase {
    public function test_authorised_grader_can_access(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        access_service::require_course_access(context_course::instance($course->id));
        $this->assertTrue(true);
    }

    public function test_student_cannot_access_gradebook_doctor(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        access_service::require_course_access(context_course::instance($course->id));
    }
}
