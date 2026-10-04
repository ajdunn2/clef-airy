<?php

namespace Tests\Unit;

use App\Services\SystemOnePayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SystemOnePayloadTest extends TestCase
{
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

    public function test_digit_only_names_are_rejected(): void
    {
        $payload = new SystemOnePayload;

        try {
            $payload->fromForm('Checkout is down.', [[
                'name' => '0',
                'type' => 'noul',
                'instructions' => 'Is this urgent?',
            ]]);
            $this->fail('A digit-only question name was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Use a letter in each question name. Names may also include numbers and underscores.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Use a letter in each option name. Names may also include numbers and underscores.');

        $payload->fromForm('Checkout is down.', [[
            'name' => 'team',
            'type' => 'choice',
            'instructions' => 'Which team?',
            'options' => [
                ['name' => '0', 'description' => 'First'],
                ['name' => 'billing', 'description' => 'Payments'],
            ],
        ]]);
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
    }
}
