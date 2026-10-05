<?php

namespace Tests\Unit;

use App\Services\SystemOnePayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SystemOnePayloadTest extends TestCase
{
    public function test_structured_content_and_partial_noul_criteria_keep_their_shapes(): void
    {
        $json = (new SystemOnePayload)->fromForm('{"id":9007199254740993,"empty":{}}', [[
            'name' => 'coverage?', 'type' => 'noul',
            'instructions' => '{"question":"Is coverage needed?","signals":["night"]}',
            'true' => '{"meaning":"Coverage needed"}',
            'false' => '',
        ], [
            'name' => 'team', 'type' => 'choice', 'instructions' => 'Which team?',
            'options' => [
                ['name' => 'customer support', 'description' => '{"covers":["tickets"]}'],
                ['name' => 'billing', 'description' => ''],
            ],
        ], [
            'name' => 'priority', 'type' => 'score', 'instructions' => 'How urgent?',
            'levels' => ['{"label":"Low"}', '["High","Immediate"]'],
        ]]);

        $body = json_decode($json);
        $this->assertStringContainsString('9007199254740993', $json);
        $this->assertInstanceOf(\stdClass::class, $body->state->empty);
        $this->assertSame('Is coverage needed?', $body->questions->{'coverage?'}->instructions->question);
        $this->assertSame('Coverage needed', $body->questions->{'coverage?'}->criteria->true->meaning);
        $this->assertFalse(property_exists($body->questions->{'coverage?' }->criteria, 'false'));
        $this->assertSame(['tickets'], $body->questions->team->criteria->{'customer support'}->covers);
        $this->assertNull($body->questions->team->criteria->billing);
        $this->assertSame('Low', $body->questions->priority->criteria[0]->label);
        $this->assertSame(['High', 'Immediate'], $body->questions->priority->criteria[1]);
    }

    public function test_duplicate_choice_names_are_rejected_instead_of_overwritten(): void
    {
        $this->expectExceptionMessage('Option names must be unique.');

        (new SystemOnePayload)->fromForm('Support needed.', [[
            'name' => 'team', 'type' => 'choice', 'instructions' => 'Which team?',
            'options' => [
                ['name' => 'billing', 'description' => 'First'],
                ['name' => 'billing', 'description' => 'Second'],
            ],
        ]]);
    }

    public function test_it_builds_noul_choice_and_score_questions(): void
    {
        $json = (new SystemOnePayload)->fromForm('Checkout is down.', [
            [
                'name' => 'urgent',
                'type' => 'noul',
                'instructions' => 'Is it urgent?',
                'true' => '',
                'false' => '',
                'choice' => '',
                'score' => '',
            ],
            [
                'name' => 'team',
                'type' => 'choice',
                'instructions' => 'Which team?',
                'true' => '',
                'false' => '',
                'choice' => "billing | Payments\ntechnical | Outages",
                'score' => '',
            ],
            [
                'name' => 'severity',
                'type' => 'score',
                'instructions' => 'How severe?',
                'true' => '',
                'false' => '',
                'choice' => '',
                'score' => "No impact\nMinor\nMajor\nCritical",
            ],
            [
                'name' => '',
                'type' => 'noul',
                'instructions' => '',
                'true' => '',
                'false' => '',
                'choice' => '',
                'score' => '',
            ],
        ]);

        $body = json_decode($json, true);

        $this->assertSame('Checkout is down.', $body['state']);
        $this->assertSame('noul', $body['questions']['urgent']['type']);
        $this->assertArrayNotHasKey('criteria', $body['questions']['urgent']);
        $this->assertSame('Outages', $body['questions']['team']['criteria']['technical']);
        $this->assertSame(['No impact', 'Minor', 'Major', 'Critical'], $body['questions']['severity']['criteria']);
    }

    public function test_it_builds_options_and_levels_from_separate_fields(): void
    {
        $json = (new SystemOnePayload)->fromForm('Checkout is down.', [
            [
                'name' => 'team',
                'type' => 'choice',
                'instructions' => 'Which team?',
                'options' => [
                    ['name' => 'billing', 'description' => 'Payments'],
                    ['name' => '', 'description' => 'ignored'],
                    ['name' => 'technical', 'description' => ''],
                ],
            ],
            [
                'name' => 'severity',
                'type' => 'score',
                'instructions' => 'How severe?',
                'levels' => ['No impact', '', 'Minor'],
            ],
        ]);

        $body = json_decode($json, true);

        $this->assertSame([
            'billing' => 'Payments',
            'technical' => null,
        ], $body['questions']['team']['criteria']);
        $this->assertSame(['No impact', 'Minor'], $body['questions']['severity']['criteria']);
    }

    public function test_numeric_and_unicode_names_are_preserved_as_object_keys(): void
    {
        $body = (new SystemOnePayload)->fromForm('Support needed.', [[
            'name' => '0',
            'type' => 'choice',
            'instructions' => 'Which team?',
            'options' => [
                ['name' => '0', 'description' => 'Billing'],
                ['name' => '1', 'description' => 'Technical'],
            ],
        ], [
            'name' => 'équipe?',
            'type' => 'noul',
            'instructions' => 'Is help needed?',
        ]]);

        $decoded = json_decode($body);
        $this->assertInstanceOf(\stdClass::class, $decoded->questions);
        $this->assertInstanceOf(\stdClass::class, $decoded->questions->{'0'}->criteria);
        $this->assertSame('Billing', $decoded->questions->{'0'}->criteria->{'0'});
        $this->assertSame('Is help needed?', $decoded->questions->{'équipe?'}->instructions);
    }

    public function test_a_choice_question_needs_two_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice questions need at least two options.');

        (new SystemOnePayload)->fromForm('Checkout is down.', [[
            'name' => 'team',
            'type' => 'choice',
            'instructions' => 'Which team?',
            'choice' => 'billing | Payments',
        ]]);
    }

    public function test_it_presents_answers_in_plain_language(): void
    {
        $view = (new SystemOnePayload)->present(json_encode([
            'model' => 'clef-flash:latest',
            'answers' => [
                'urgent' => ['type' => 'noul', 'noul' => 0.9548074686443766],
                'team' => [
                    'type' => 'choice',
                    'choice' => 'technical',
                    'probabilities' => [
                        'billing' => 0.04896738345411926,
                        'technical' => 0.9372828067572471,
                    ],
                    'confidence' => 0.7566340796231583,
                ],
                'severity' => [
                    'type' => 'score',
                    'score' => 2.718684304573567,
                    'legend' => ['0' => 'No impact', '1' => 'Minor', '2' => 'Major', '3' => 'Critical'],
                    'probabilities' => ['2' => 0.20715689060811115, '3' => 0.7633238057850966],
                    'confidence' => 0.5262793898820344,
                ],
            ],
            'usage' => ['input_tokens' => 346, 'output_tokens' => 0],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('clef-flash:latest', $view['model']);
        $this->assertSame('Yes', $view['answers'][0]['headline']);
        $this->assertSame('95% yes', $view['answers'][0]['detail']);
        $this->assertSame('technical', $view['answers'][1]['headline']);
        $this->assertSame('76%', $view['answers'][1]['confidence']);
        $this->assertTrue($view['answers'][1]['rows'][1]['selected']);
        $this->assertSame(94, $view['answers'][1]['rows'][1]['share']);
        $this->assertSame(76, $view['answers'][2]['rows'][1]['share']);
        $this->assertSame('Major toward Critical', $view['answers'][2]['headline']);
        $this->assertSame('2.7', $view['answers'][2]['detail']);
        $this->assertSame('346 tokens in', $view['usage']);
        $this->assertNull($view['error']);
    }

    public function test_it_presents_api_errors_without_answers(): void
    {
        $view = (new SystemOnePayload)->present('{"error": "invalid character \'}\' looking for beginning of object key string"}');

        $this->assertSame('invalid character \'}\' looking for beginning of object key string', $view['error']);
        $this->assertSame([], $view['answers']);
        $this->assertNull($view['usage']);
    }
}
