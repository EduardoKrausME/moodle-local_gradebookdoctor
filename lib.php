<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Local plugin callbacks.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the Gradebook Doctor entry to course navigation for authorised graders.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function local_gradebookdoctor_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/gradebookdoctor:view', $context)) {
        return;
    }
    if (!has_capability('moodle/grade:viewall', $context) ||
        !has_capability('gradereport/grader:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_gradebookdoctor'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_gradebookdoctor',
        new pix_icon('i/report', '')
    );
}
