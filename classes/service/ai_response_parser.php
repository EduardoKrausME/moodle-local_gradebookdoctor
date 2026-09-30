<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) {
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
