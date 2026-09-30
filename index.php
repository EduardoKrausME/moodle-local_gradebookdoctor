<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Gradebook Doctor dashboard.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_gradebookdoctor\service\access_service;
use local_gradebookdoctor\service\ai_service;
use local_gradebookdoctor\service\diagnostic_service;
use local_gradebookdoctor\service\gradebook_inspector;

$courseid = required_param('courseid', PARAM_INT);
$mode = optional_param('mode', 'explain', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$userida = optional_param('userida', 0, PARAM_INT);
$useridb = optional_param('useridb', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$run = optional_param('run', 0, PARAM_BOOL);

if (!in_array($mode, ['explain', 'diagnose', 'compare'], true)) {
    $mode = 'explain';
}

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
access_service::require_course_access($context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $courseid, 'mode' => $mode]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_gradebookdoctor'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/gradebookdoctor/styles.css');

$inspector = new gradebook_inspector($courseid);
$aiservice = new ai_service();
$items = $inspector->get_items();
$users = get_enrolled_users(
    $context,
    '',
    0,
    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
    'u.lastname ASC, u.firstname ASC',
    0,
    0,
    true
);

$itemoptions = [];
foreach ($items as $item) {
    $itemoptions[] = [
        'id' => (int)$item->id,
        'label' => $inspector->item_label($item),
        'selected' => (int)$item->id === $itemid,
    ];
}

$canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
$useroptions = [];
foreach ($users as $user) {
    $useroptions[] = [
        'id' => (int)$user->id,
        'name' => fullname($user, $canviewfullnames),
        'selected' => (int)$user->id === $userid,
        'selecteda' => (int)$user->id === $userida,
        'selectedb' => (int)$user->id === $useridb,
    ];
}

$baseurl = new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $courseid]);
$templatecontext = [
    'courseid' => $courseid,
    'sesskey' => sesskey(),
    'mode' => $mode,
    'isexplaintab' => $mode === 'explain',
    'isdiagnosetab' => $mode === 'diagnose',
    'iscomparetab' => $mode === 'compare',
    'explainurl' => (new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $courseid, 'mode' => 'explain']))->out(false),
    'diagnoseurl' => (new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $courseid, 'mode' => 'diagnose']))->out(false),
    'compareurl' => (new moodle_url('/local/gradebookdoctor/index.php', ['courseid' => $courseid, 'mode' => 'compare']))->out(false),
    'actionurl' => $baseurl->out(false),
    'users' => $useroptions,
    'items' => $itemoptions,
    'hasusers' => !empty($useroptions),
    'hasitems' => !empty($itemoptions),
    'result' => null,
];

$ispostrun = $run && data_submitted() && confirm_sesskey();
if ($ispostrun && $mode === 'explain' && $userid && $itemid) {
    access_service::require_course_user($courseid, $userid);
    $snapshot = $inspector->explain($userid, $itemid);
    $ai = $aiservice->explain_calculation($snapshot);
    $user = $users[$userid] ?? $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    $templatecontext['result'] = [
        'type_explain' => true,
        'title' => get_string('result:explainfor', 'local_gradebookdoctor', fullname($user, $canviewfullnames)),
        'moodlecalculation' => true,
        'rows' => $inspector->flatten_for_display($snapshot),
        'finalgrade' => $snapshot['finalgrade'] === null ? '—' : format_float($snapshot['finalgrade'], 5, true, true),
        'range' => format_float($snapshot['grademin'], 5, true, true) . '–' . format_float($snapshot['grademax'], 5, true, true),
        'rootlabel' => $snapshot['label'],
        'aiexplanationhtml' => $ai['text'] !== null ? format_text($ai['text'], FORMAT_PLAIN, ['context' => $context]) : null,
        'aierror' => $ai['error'],
    ];
} else if ($ispostrun && $mode === 'diagnose') {
    $diagnosis = (new diagnostic_service($courseid))->diagnose();
    $ai = $aiservice->explain_diagnosis($diagnosis);
    $aifindings = [];
    $deterministicbycode = [];
    foreach ($diagnosis['findings'] as $finding) {
        $deterministicbycode[$finding['code']] = $finding;
    }
    if (!empty($ai['data']['findings']) && is_array($ai['data']['findings'])) {
        foreach ($ai['data']['findings'] as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            $code = clean_param((string)($finding['code'] ?? ''), PARAM_TEXT);
            if (!isset($deterministicbycode[$code])) {
                continue;
            }
            $aifindings[] = [
                'code' => $code,
                'explanation' => clean_param((string)($finding['explanation'] ?? ''), PARAM_TEXT),
                'consequence' => clean_param((string)($finding['consequence'] ?? ''), PARAM_TEXT),
                'attention' => $deterministicbycode[$code]['attentionlabel'],
            ];
        }
    }
    $templatecontext['result'] = [
        'type_diagnose' => true,
        'summary' => $diagnosis['summary'],
        'findings' => $diagnosis['findings'],
        'hasfindings' => !empty($diagnosis['findings']),
        'aisummary' => !empty($ai['data']['summary']) ? clean_param((string)$ai['data']['summary'], PARAM_TEXT) : null,
        'aifindings' => $aifindings,
        'hasaifindings' => !empty($aifindings),
        'aierror' => $ai['error'],
    ];
} else if ($ispostrun && $mode === 'compare' && $userida && $useridb && $itemid) {
    access_service::require_course_user($courseid, $userida);
    access_service::require_course_user($courseid, $useridb);
    $comparison = $inspector->compare($userida, $useridb, $itemid);
    $ai = $aiservice->explain_comparison($comparison);
    $ua = $users[$userida] ?? $DB->get_record('user', ['id' => $userida], '*', MUST_EXIST);
    $ub = $users[$useridb] ?? $DB->get_record('user', ['id' => $useridb], '*', MUST_EXIST);
    $differences = [];
    foreach ($comparison['differences'] as $difference) {
        $differences[] = [
            'itemid' => $difference['itemid'],
            'label' => $difference['label'],
            'afinal' => $difference['a']['finalgrade'] === null ? '—' : format_float($difference['a']['finalgrade'], 5, true, true),
            'bfinal' => $difference['b']['finalgrade'] === null ? '—' : format_float($difference['b']['finalgrade'], 5, true, true),
            'astatus' => $difference['a']['aggregationstatus'] ?: get_string('status:notrecorded', 'local_gradebookdoctor'),
            'bstatus' => $difference['b']['aggregationstatus'] ?: get_string('status:notrecorded', 'local_gradebookdoctor'),
            'aoverride' => $difference['a']['overridden'],
            'boverride' => $difference['b']['overridden'],
            'aexcluded' => $difference['a']['excluded'],
            'bexcluded' => $difference['b']['excluded'],
        ];
    }
    $templatecontext['result'] = [
        'type_compare' => true,
        'studentaname' => fullname($ua, $canviewfullnames),
        'studentbname' => fullname($ub, $canviewfullnames),
        'afinal' => $comparison['student_a']['finalgrade'] === null ? '—' : format_float($comparison['student_a']['finalgrade'], 5, true, true),
        'bfinal' => $comparison['student_b']['finalgrade'] === null ? '—' : format_float($comparison['student_b']['finalgrade'], 5, true, true),
        'rootlabel' => $comparison['student_a']['label'],
        'differences' => $differences,
        'hasdifferences' => !empty($differences),
        'aiexplanationhtml' => $ai['text'] !== null ? format_text($ai['text'], FORMAT_PLAIN, ['context' => $context]) : null,
        'aierror' => $ai['error'],
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_gradebookdoctor/dashboard', $templatecontext);
echo $OUTPUT->footer();
