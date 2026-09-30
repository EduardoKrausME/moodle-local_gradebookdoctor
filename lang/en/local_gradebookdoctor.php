<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Gradebook Doctor';
$string['gradebookdoctor:view'] = 'Use Gradebook Doctor';
$string['privacy:metadata'] = 'Gradebook Doctor does not store personal data, prompts, or AI responses. It reads gradebook data only while an authorised report is being generated.';
$string['intro'] = 'Inspect the gradebook using Moodle\'s own calculated values first, then use AI only to explain those facts.';
$string['tab:explain'] = 'Explain this grade';
$string['tab:diagnose'] = 'Diagnose gradebook';
$string['tab:compare'] = 'Compare two students';
$string['selectuser'] = 'Student';
$string['selectusera'] = 'Student A';
$string['selectuserb'] = 'Student B';
$string['selectitem'] = 'Grade item or total';
$string['run:explain'] = 'Explain grade';
$string['run:diagnose'] = 'Run diagnosis';
$string['run:compare'] = 'Compare totals';
$string['result:explainfor'] = 'Calculation for {$a}';
$string['section:moodlecalculation'] = 'Moodle calculation';
$string['section:aiexplanation'] = 'AI explanation';
$string['section:deterministicdiagnosis'] = 'Deterministic diagnosis';
$string['section:aidiagnosis'] = 'AI explanation of findings';
$string['section:comparison'] = 'Moodle comparison';
$string['finalgrade'] = 'Final grade';
$string['range'] = 'Range';
$string['item'] = 'Item';
$string['type'] = 'Type';
$string['rawgrade'] = 'Raw grade';
$string['aggregation'] = 'Aggregation';
$string['status'] = 'Use in parent total';
$string['weight'] = 'Effective weight';
$string['flags'] = 'Flags';
$string['formula'] = 'Formula';
$string['edit'] = 'Open gradebook setting';
$string['flag:hidden'] = 'Hidden';
$string['flag:excluded'] = 'Excluded';
$string['flag:overridden'] = 'Overridden';
$string['flag:locked'] = 'Locked';
$string['flag:needsupdate'] = 'Needs recalculation';
$string['flag:extracredit'] = 'Extra credit';
$string['status:notrecorded'] = 'Not recorded';
$string['formula:dependency'] = 'Formula input: {$a}';
$string['unnameditem'] = 'Grade item #{$a}';
$string['noitems'] = 'No grade items are available in this course.';
$string['nousers'] = 'No enrolled users are available for this report.';
$string['nofindings'] = 'No suspicious configuration was found by the deterministic checks implemented by this version.';
$string['nodifferences'] = 'No differences were found in the compared gradebook facts for this item.';
$string['diagnosis:summary'] = '{$a->findings} findings: {$a->review} to review and {$a->info} informational.';
$string['diagnosis:categories'] = 'Categories';
$string['diagnosis:items'] = 'Grade items';
$string['diagnosis:findings'] = 'Findings';
$string['attention:review'] = 'Review';
$string['attention:info'] = 'Info';
$string['finding:emptycategory'] = 'The category has no direct grade items and no child categories.';
$string['finding:keepanddrop'] = 'The category keeps the highest {$a->keep} item(s) and also drops the lowest {$a->drop} item(s). Review whether both rules are intentional.';
$string['finding:manualweights'] = '{$a->count} item(s) have manual Natural aggregation weights. Their stored manual weight sum is {$a->sum}; zero-weight item IDs: {$a->zero}.';
$string['finding:needsupdate'] = 'Moodle marks this grade item as needing recalculation. The plugin will not force a regrade automatically.';
$string['finding:itemlocked'] = 'The grade item is locked at item level.';
$string['finding:adjustment'] = 'A grade adjustment is active: multiplicator {$a->mult}, offset {$a->plus}.';
$string['finding:invalidrange'] = 'The numeric grade range is unusual: minimum {$a->min}, maximum {$a->max}.';
$string['finding:formulainvalid'] = 'Moodle formula validation failed: {$a}';
$string['finding:formulaselfreference'] = 'The stored calculation contains a reference to its own grade item ID.';
$string['finding:formulamissingreference'] = 'The stored calculation references grade item #{$a}, but that item does not exist in this course.';
$string['finding:formulaunresolved'] = 'The stored calculation still contains an unresolved [[idnumber]]-style reference.';
$string['finding:nofinalgrades'] = 'No non-null final grades currently exist for this item. This may be expected for a new or unused activity.';
$string['finding:overrides'] = '{$a} user grade(s) are manually overridden for this item.';
$string['finding:lockedgrades'] = '{$a} user grade(s) are locked for this item.';
$string['finding:hiddenbutused'] = 'This item is hidden and Moodle records it as used or extra credit in {$a} user aggregation(s).';
$string['finding:weightconcentration'] = 'Moodle records an effective weight of {$a->weight} for "{$a->item}" in at least one aggregation where this category has three or more contributors.';
$string['aggregation:mean'] = 'Mean of grades';
$string['aggregation:weightedmean'] = 'Weighted mean of grades';
$string['aggregation:simpleweightedmean'] = 'Simple weighted mean of grades';
$string['aggregation:extracreditmean'] = 'Mean of grades with extra credit';
$string['aggregation:median'] = 'Median of grades';
$string['aggregation:min'] = 'Lowest grade';
$string['aggregation:max'] = 'Highest grade';
$string['aggregation:mode'] = 'Mode of grades';
$string['aggregation:natural'] = 'Natural';
$string['aggregation:unknown'] = 'Unknown aggregation';
$string['error:bridgeunavailable'] = 'local_ai_bridge is not available. Moodle calculation data is still shown.';
$string['error:aifailed'] = 'AI explanation is unavailable for this request. Check AI Bridge tenant, purpose, route, credits, and user permissions.';
$string['error:invalidairesponse'] = 'AI Bridge returned a response, but it was not the required valid JSON object. Deterministic findings are still shown.';
$string['error:usernotincourse'] = 'The selected user is not an active enrolled user in this course.';
$string['error:invalidgradeitem'] = 'The selected grade item does not belong to this course.';
$string['compare:studenta'] = 'Student A: {$a}';
$string['compare:studentb'] = 'Student B: {$a}';
$string['compare:item'] = 'Compared item: {$a}';
$string['compare:statusa'] = 'A status';
$string['compare:statusb'] = 'B status';
$string['compare:gradea'] = 'A grade';
$string['compare:gradeb'] = 'B grade';
$string['compare:flags'] = 'Difference flags';
$string['compare:overridea'] = 'A overridden';
$string['compare:overrideb'] = 'B overridden';
$string['compare:excludeda'] = 'A excluded';
$string['compare:excludedb'] = 'B excluded';
$string['ai:boundary'] = 'The AI text below explains Moodle data; it is not the source of the grade value.';
$string['deterministic:boundary'] = 'Values, statuses, weights and flags below come from Moodle gradebook records and core grade classes. Gradebook Doctor does not recalculate the final grade.';
$string['diagnosis:boundary'] = 'Findings below come from deterministic checks. “Review” means the configuration deserves attention, not that it is necessarily incorrect.';
