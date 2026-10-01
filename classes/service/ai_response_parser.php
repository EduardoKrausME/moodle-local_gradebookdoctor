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

use JsonException;

/**
 * Parses structured AI responses without trusting them as gradebook facts.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_response_parser {
    /**
     * Decode a JSON object, accepting an optional Markdown code fence.
     *
     * @param string $text Raw model response.
     * @return array|null
     */
    public static function parse_json_object(string $text): ?array {
        $text = trim($text);
        if (preg_match('/^\x60{3}(?:json)?\s*(.*?)\s*\x60{3}$/is', $text, $matches)) {
            $text = trim($matches[1]);
        }

        try {
            $decoded = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }
        return $decoded;
    }
}
