<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_gradebookdoctor;

use local_gradebookdoctor\service\ai_response_parser;

/**
 * AI response parser tests.
 *
 * @package local_gradebookdoctor
 * @covers \local_gradebookdoctor\service\ai_response_parser
 */
final class ai_response_parser_test extends \advanced_testcase {
    public function test_parses_plain_json_object(): void {
        $result = ai_response_parser::parse_json_object('{"summary":"ok","findings":[]}');
        $this->assertSame('ok', $result['summary']);
        $this->assertSame([], $result['findings']);
    }

    public function test_parses_fenced_json_object(): void {
        $result = ai_response_parser::parse_json_object("```json\n{\"summary\":\"ok\"}\n```");
        $this->assertSame('ok', $result['summary']);
    }

    public function test_rejects_invalid_json_and_json_list(): void {
        $this->assertNull(ai_response_parser::parse_json_object('not json'));
        $this->assertNull(ai_response_parser::parse_json_object('[1,2,3]'));
    }
}
