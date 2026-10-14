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
     * Get only active enrolled users the current grader is permitted to inspect.
     *
     * For separate groups, an observer without accessallgroups can see students
     * sharing at least one of their groups. The group filter is enforced on the
     * server to protect direct POST requests as well as the displayed user lists.
     *
     * @param int $courseid Course id.
     * @param string $fields Fields for the enrolled-user query.
     * @param string $sort Sort expression for the enrolled-user query.
     * @return array Users visible to the current grader, keyed by user id.
     */
    public static function get_course_users(int $courseid, string $fields = 'u.id', string $sort = ''): array {
        global $DB, $USER;

        $context = context_course::instance($courseid);
        $course = get_course($courseid);
        $allowedids = null;
        if (groups_get_course_groupmode($course) === SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $context)) {
            $groups = groups_get_all_groups($courseid, $USER->id, 0, 'g.id');
            if (empty($groups)) {
                return [];
            }
            [$insql, $params] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'gbdgroup');
            $memberids = $DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {groups_members} WHERE groupid $insql",
                $params
            );
            if (empty($memberids)) {
                return [];
            }
            $allowedids = array_fill_keys(array_map('intval', $memberids), true);
        }

        $users = get_enrolled_users($context, '', 0, $fields, $sort, 0, 0, true);
        return $allowedids === null ? $users : array_intersect_key($users, $allowedids);
    }

    /**
     * Require an active enrolled user visible under the course group rules.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return void
     */
    public static function require_course_user(int $courseid, int $userid): void {
        $users = self::get_course_users($courseid);
        if (!isset($users[$userid])) {
            throw new moodle_exception('error:usernotincourse', 'local_gradebookdoctor');
        }
    }
}
