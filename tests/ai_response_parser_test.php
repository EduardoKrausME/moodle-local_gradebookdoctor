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

namespace local_gradebookdoctor;

use advanced_testcase;
use local_gradebookdoctor\service\ai_response_parser;

/**
 * AI response parser tests.
 *
 * @package local_gradebookdoctor
 * @covers \local_gradebookdoctor\service\ai_response_parser
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ai_response_parser_test extends advanced_testcase {
    /**
     * Method test_parses_plain_json_object.
     *
     * @return void Return value.
     */
    public function test_parses_plain_json_object(): void {
        $result = ai_response_parser::parse_json_object('{"summary":"ok","findings":[]}');
        $this->assertSame('ok', $result['summary']);
        $this->assertSame([], $result['findings']);
    }

    /**
     * Method test_parses_fenced_json_object.
     *
     * @return void Return value.
     */
    public function test_parses_fenced_json_object(): void {
        $result = ai_response_parser::parse_json_object("\x60{3}json\n{\"summary\":\"ok\"}\n\x60{3}");
        $this->assertSame('ok', $result['summary']);
    }

    /**
     * Method test_rejects_invalid_json_and_json_list.
     *
     * @return void Return value.
     */
    public function test_rejects_invalid_json_and_json_list(): void {
        $this->assertNull(ai_response_parser::parse_json_object('not json'));
        $this->assertNull(ai_response_parser::parse_json_object('[1,2,3]'));
    }
}
