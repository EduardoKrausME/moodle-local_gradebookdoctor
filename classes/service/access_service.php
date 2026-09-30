<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor\service;

use context_course;
use moodle_exception;

/**
 * Centralises access control for gradebook data.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_service {
    /**
     * Require the same core grade viewing permissions used by the grader report,
     * in addition to this plugin's own capability.
     *
     * @param context_course $context Course context.
     * @return void
     */
    public static function require_course_access(context_course $context): void {
        require_capability('local/gradebookdoctor:view', $context);
        require_capability('gradereport/grader:view', $context);
        require_capability('moodle/grade:viewall', $context);
    }

    /**
     * Assert that the target user belongs to the course context.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return void
     */
    public static function require_course_user(int $courseid, int $userid): void {
        $context = context_course::instance($courseid);
        $users = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        if (!isset($users[$userid])) {
            throw new moodle_exception('error:usernotincourse', 'local_gradebookdoctor');
        }
    }
}
