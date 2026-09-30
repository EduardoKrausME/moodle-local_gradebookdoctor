<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider.
 *
 * The plugin intentionally stores no user-specific data, prompts, or AI responses.
 * Grade data is read directly from Moodle's gradebook when a report is requested.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Explain why this plugin has no personal data of its own.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
