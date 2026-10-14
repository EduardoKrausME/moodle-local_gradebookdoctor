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

namespace local_gradebookdoctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy provider.
 *
 * The plugin stores no user-specific reports, but an authorised request can
 * send gradebook facts to an external AI provider through local_ai_bridge.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {
    /**
     * Describe gradebook data routed to the configured external AI service.
     *
     * @param collection $collection Privacy metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('aiprovider', [
            'itemid' => 'privacy:metadata:aiprovider:itemid',
            'label' => 'privacy:metadata:aiprovider:label',
            'itemtype' => 'privacy:metadata:aiprovider:itemtype',
            'itemmodule' => 'privacy:metadata:aiprovider:itemmodule',
            'grademin' => 'privacy:metadata:aiprovider:grademin',
            'grademax' => 'privacy:metadata:aiprovider:grademax',
            'rawgrade' => 'privacy:metadata:aiprovider:rawgrade',
            'finalgrade' => 'privacy:metadata:aiprovider:finalgrade',
            'aggregation' => 'privacy:metadata:aiprovider:aggregation',
            'aggregationstatus' => 'privacy:metadata:aiprovider:aggregationstatus',
            'aggregationweight' => 'privacy:metadata:aiprovider:aggregationweight',
            'weightpercent' => 'privacy:metadata:aiprovider:weightpercent',
            'formula' => 'privacy:metadata:aiprovider:formula',
            'excluded' => 'privacy:metadata:aiprovider:excluded',
            'overridden' => 'privacy:metadata:aiprovider:overridden',
            'locked' => 'privacy:metadata:aiprovider:locked',
            'hidden' => 'privacy:metadata:aiprovider:hidden',
            'needsupdate' => 'privacy:metadata:aiprovider:needsupdate',
            'extracredit' => 'privacy:metadata:aiprovider:extracredit',
        ], 'privacy:metadata:aiprovider');

        $collection->add_plugin_link('local_ai_bridge', [], 'privacy:metadata:local_ai_bridge');
        return $collection;
    }
}
