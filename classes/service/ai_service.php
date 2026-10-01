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

use local_ai_bridge\api;
use Throwable;

/**
 * AI Bridge integration.
 *
 * All model traffic is routed exclusively through local_ai_bridge.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_service {
    /** AI Bridge purpose used by this plugin. */
    private const PURPOSE = 'gradebookdoctor-explain';

    /** Maximum serialized deterministic payload sent to the model. */
    private const MAX_PAYLOAD_BYTES = 70000;

    /**
     * Produce a human explanation for a deterministic calculation snapshot.
     *
     * @param array $snapshot Calculation snapshot.
     * @return array{text:?string,error:?string}
     */
    public function explain_calculation(array $snapshot): array {
        $payload = $this->encode_payload($this->minimise_snapshot($snapshot));
        $prompt = <<<PROMPT
You are explaining a Moodle grade calculation to an authorised teacher.
Treat the JSON below as the only source of truth. Never recalculate or replace Moodle's final values.
Explain which grade items were used, excluded, dropped, missing, or extra credit; explain effective weights,
aggregation, formulas, overrides, locks, hidden state, and intermediate category totals when present.
If something cannot be concluded from the JSON, say that it cannot be concluded.
Do not judge the student. Do not infer effort, ability, intent, personality, health, or academic integrity.
Use concise, practical language and refer to item IDs or labels present in the payload.

Moodle calculation snapshot:
{$payload}
PROMPT;
        return $this->generate_text($prompt);
    }

    /**
     * Explain deterministic diagnostic findings and require JSON from the model.
     *
     * @param array $diagnosis Deterministic diagnosis.
     * @return array{data:?array,error:?string}
     */
    public function explain_diagnosis(array $diagnosis): array {
        $payload = $this->encode_payload($this->minimise_diagnosis($diagnosis));
        $prompt = <<<PROMPT
You are explaining deterministic Moodle gradebook diagnostics to an authorised teacher.
The findings in the JSON are facts produced by PHP and Moodle APIs. Do not invent additional findings.
Return ONLY one valid JSON object, with no Markdown fences, in this shape:
{
  "summary": "short factual summary",
  "findings": [
    {
      "code": "must exactly match an input finding code",
      "explanation": "what the configuration means",
      "consequence": "possible gradebook consequence, carefully phrased",
      "attention": "info|review"
    }
  ]
}
Do not recompute grades and do not claim a configuration is wrong merely because it is unusual.

Deterministic findings:
{$payload}
PROMPT;
        $result = $this->generate_text($prompt);
        if ($result['error'] !== null) {
            return ['data' => null, 'error' => $result['error']];
        }

        $data = ai_response_parser::parse_json_object((string)$result['text']);
        if ($data === null) {
            return ['data' => null, 'error' => get_string('error:invalidairesponse', 'local_gradebookdoctor')];
        }
        return ['data' => $data, 'error' => null];
    }

    /**
     * Explain deterministic differences between two students without making judgements.
     *
     * @param array $comparison Comparison payload.
     * @return array{text:?string,error:?string}
     */
    public function explain_comparison(array $comparison): array {
        $payload = $this->encode_payload($this->minimise_comparison($comparison));
        $prompt = <<<PROMPT
Explain the factual differences between Student A and Student B in this Moodle gradebook comparison.
Use only the supplied JSON. Explain differences in included/excluded grades, overrides, missing values,
weights, category totals, formulas, and final totals. Do not judge either student and do not infer ability,
effort, motivation, intent, personality, health, or academic integrity. Never recalculate the Moodle values.

Comparison:
{$payload}
PROMPT;
        return $this->generate_text($prompt);
    }

    /**
     * Send a prompt through the only supported AI entry point.
     *
     * @param string $prompt Prompt text.
     * @return array{text:?string,error:?string}
     */
    private function generate_text(string $prompt): array {
        if (!class_exists('\\local_ai_bridge\\api')) {
            return ['text' => null, 'error' => get_string('error:bridgeunavailable', 'local_gradebookdoctor')];
        }

        try {
            $response = api::generate(
                self::PURPOSE,
                [['role' => 'user', 'content' => $prompt]]
            );
            return ['text' => trim($response->text), 'error' => null];
        } catch (Throwable $e) {
            debugging('Gradebook Doctor AI Bridge error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['text' => null, 'error' => get_string('error:aifailed', 'local_gradebookdoctor')];
        }
    }

    /**
     * Keep the explanation payload compact and remove UI-only fields.
     *
     * @param array $snapshot Snapshot.
     * @return array
     */
    private function minimise_snapshot(array $snapshot): array {
        $allowed = [
            'itemid', 'label', 'itemtype', 'itemmodule', 'grademin', 'grademax', 'rawgrade', 'finalgrade',
            'aggregation', 'aggregationstatus', 'aggregationweight', 'weightpercent', 'formula', 'excluded',
            'overridden', 'locked', 'hidden', 'needsupdate', 'extracredit', 'children', 'dependencies',
        ];
        $walk = function (array $node) use (&$walk, $allowed): array {
            $result = [];
            foreach ($allowed as $key) {
                if (!array_key_exists($key, $node)) {
                    continue;
                }
                if ($key === 'children' || $key === 'dependencies') {
                    $result[$key] = [];
                    foreach ((array)$node[$key] as $child) {
                        if (is_array($child)) {
                            $result[$key][] = $walk($child);
                        }
                    }
                } else {
                    $result[$key] = $node[$key];
                }
            }
            return $result;
        };
        return $walk($snapshot);
    }

    /**
     * Remove UI-only fields and URLs from deterministic diagnosis data.
     *
     * @param array $diagnosis Diagnosis.
     * @return array
     */
    private function minimise_diagnosis(array $diagnosis): array {
        $result = ['summary' => $diagnosis['summary'] ?? [], 'findings' => []];
        foreach ((array)($diagnosis['findings'] ?? []) as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            $result['findings'][] = [
                'code' => (string)($finding['code'] ?? ''),
                'type' => (string)($finding['type'] ?? ''),
                'attention' => (string)($finding['attention'] ?? ''),
                'label' => (string)($finding['label'] ?? ''),
                'evidence' => (string)($finding['evidence'] ?? ''),
            ];
        }
        return $result;
    }

    /**
     * Remove UI-only snapshot fields from a two-user comparison.
     *
     * @param array $comparison Comparison.
     * @return array
     */
    private function minimise_comparison(array $comparison): array {
        return [
            'itemid' => $comparison['itemid'] ?? null,
            'student_a' => isset($comparison['student_a']) && is_array($comparison['student_a'])
                ? $this->minimise_snapshot($comparison['student_a'])
                : [],
            'student_b' => isset($comparison['student_b']) && is_array($comparison['student_b'])
                ? $this->minimise_snapshot($comparison['student_b'])
                : [],
            'differences' => array_values(array_map(static function (array $difference): array {
                return [
                    'itemid' => $difference['itemid'] ?? null,
                    'label' => $difference['label'] ?? '',
                    'a' => $difference['a'] ?? [],
                    'b' => $difference['b'] ?? [],
                ];
            }, array_filter((array)($comparison['differences'] ?? []), 'is_array'))),
        ];
    }

    /**
     * Encode data for a prompt with a hard size ceiling.
     *
     * @param array $data Data.
     * @return string
     */
    private function encode_payload(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return '{}';
        }
        if (strlen($json) <= self::MAX_PAYLOAD_BYTES) {
            return $json;
        }
        $truncated = [
            'truncated' => true,
            'note' => 'Gradebook Doctor truncated an oversized deterministic payload before sending it to AI.',
            'excerpt' => substr($json, 0, self::MAX_PAYLOAD_BYTES - 1000),
        ];
        return (string)json_encode($truncated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
