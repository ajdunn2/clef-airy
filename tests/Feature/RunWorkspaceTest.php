<?php

namespace Tests\Feature;

use App\Http\Controllers\RunController;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWorkspaceTest extends TestCase
{
    public function test_jev_form_requests_allow_more_than_26_choice_options(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->put('/configuration', [
            'api_url' => 'https://api.typesafe.ai', 'auth_type' => 'bearer',
            'password' => 'test-api-key', 'model' => 'jev-latest',
        ]);
        $options = array_map(fn (int $index): array => [
            'name' => 'team '.$index, 'description' => 'Support team '.$index,
        ], range(1, 27));

        $this->postJson('/run', [
            'method' => 'POST', 'path' => '/v1/systemone', 'body_mode' => 'form',
            'state' => 'Support needed.',
            'questions' => [[
                'name' => 'team', 'type' => 'choice', 'instructions' => 'Which team?', 'options' => $options,
            ]],
        ])->assertOk();

        Http::assertSent(fn ($request) => count($request['questions']['team']['criteria']) === 27
            && $request['questions']['team']['criteria']['team 27'] === 'Support team 27');
    }

    public function test_form_requests_preserve_question_and_option_names_with_spaces(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('{}')]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->postJson('/run', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'The night desk needs support coverage.',
            'questions' => [
                ['name' => 'urgent', 'type' => 'noul', 'instructions' => 'Is this urgent?'],
                [
                    'name' => 'support team',
                    'type' => 'choice',
                    'instructions' => 'Which team should handle this?',
                    'options' => [
                        ['name' => 'billing', 'description' => 'Billing support'],
                        ['name' => 'technical', 'description' => 'Technical support'],
                        ['name' => 'sales', 'description' => 'Sales support'],
                        ['name' => 'customer support', 'description' => 'customer support'],
                    ],
                ],
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['state'] === 'The night desk needs support coverage.'
            && $request['questions']['urgent']['instructions'] === 'Is this urgent?'
            && $request['questions']['support team']['criteria'] === [
                'billing' => 'Billing support', 'technical' => 'Technical support', 'sales' => 'Sales support', 'customer support' => 'customer support',
            ]);
    }

    public function test_run_starts_blank_with_its_sidebar_link_selected(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->get('/run')->assertOk()->assertSee('Example #1')->assertSee('Example #2');
        $this->assertSame(3, substr_count($response->getContent(), 'Compose a request'));
        $this->assertStringNotContainsString('Compose Request', $response->getContent());

        $this->assertMatchesRegularExpression('/<textarea[^>]*id="state"[^>]*><\/textarea>/', $response->getContent());
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="body"[^>]*><\/textarea>/', $response->getContent());
        $this->assertMatchesRegularExpression('/href="[^"]*\/run"\s+aria-current="page"/', $response->getContent());
        $this->assertMatchesRegularExpression('/Example #3\s*<\/a>\s*<button[^>]*>\s*Hide examples\s*<\/button>/', $response->getContent());
        $this->assertMatchesRegularExpression('/data-add-question[^>]*>\s*<svg\b[\s\S]*?M22 17a2 2 0 0 1-2 2H6\.828[\s\S]*?<\/svg>\s*Add question<\/button>/', $response->getContent());
        $this->assertMatchesRegularExpression('/Add question<\/button>\s*<button[^>]*data-duplicate-question[^>]*>\s*<svg\b[\s\S]*?M5 7a2 2 0 0 0-2 2v11[\s\S]*?<\/svg>\s*Duplicate<\/button>/', $response->getContent());
        $this->assertSame(2, preg_match_all('/data-send[^>]*>\s*<svg\b[\s\S]*?M14\.536 21\.686[\s\S]*?<\/svg>\s*<span[^>]*>Send<\/span>/', $response->getContent()));
        $this->assertMatchesRegularExpression('/copyRequest[\s\S]*?<svg\b[\s\S]*?M4 16c-1\.1 0-2-\.9-2-2V4[\s\S]*?<\/svg>\s*<span[^>]*>Copy request<\/span>/', $response->getContent());
        $this->assertMatchesRegularExpression('/copyResponse[\s\S]*?<svg\b[\s\S]*?M4 16c-1\.1 0-2-\.9-2-2V4[\s\S]*?<\/svg>\s*<span[^>]*>Copy response<\/span>/', $response->getContent());
        $this->assertMatchesRegularExpression('/x-show="hasResponse"[\s\S]*?data-download-exchange[\s\S]*?<svg\b[\s\S]*?M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4[\s\S]*?<\/svg>\s*<span[^>]*>Download JSON<\/span>/', $response->getContent());
        $this->assertMatchesRegularExpression('/name="body_mode"[^>]*value="form"[\s\S]*?<svg\b[\s\S]*?M4 14h6[\s\S]*?<\/svg>\s*Form/', $response->getContent());
        $this->assertMatchesRegularExpression('/name="body_mode"[^>]*value="json"[\s\S]*?<svg\b[\s\S]*?M8 3H7a2 2 0 0 0-2 2v5[\s\S]*?<\/svg>\s*JSON/', $response->getContent());
        $response->assertSeeInOrder(['POST', '/v1/systemone', 'Edit']);
        $this->assertMatchesRegularExpression('/<select[^>]*id="method"[^>]*name="method"/', $response->getContent());
        $this->assertMatchesRegularExpression('/<input[^>]*id="path"[^>]*name="path"[^>]*value="\/v1\/systemone"/', $response->getContent());
        preg_match('/<input\b[^>]*id="path"[^>]*>/', $response->getContent(), $pathInput);
        $this->assertStringNotContainsString('required', $pathInput[0]);
    }

    public function test_run_example_loads_the_example_and_selects_only_its_sidebar_link(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->get('/run/example')->assertOk();

        $this->assertMatchesRegularExpression('/href="[^"]*\/run\/example"\s+aria-current="page"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/run"\s+aria-current="page"/', $response->getContent());
        $this->assertStringContainsString('Checkout has been failing', $response->getContent());
        $response->assertSee('value="urgent"', false)->assertSee('value="team"', false)->assertSee('value="severity"', false);
    }

    public function test_second_example_loads_a_sci_fi_request_and_selects_its_link(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->get('/run/example-2')->assertOk()->assertSee('Asterion');

        $this->assertMatchesRegularExpression('/href="[^"]*\/run\/example-2"\s+aria-current="page"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/run\/example"\s+aria-current="page"/', $response->getContent());
        $response->assertSee('value="raise_alert"', false)->assertSee('value="next_move"', false)->assertSee('value="threat_level"', false);
    }

    public function test_second_example_can_be_sent_and_keeps_its_page(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('{}')]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->post('/run/example-2', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => RunController::SCI_FI_STATE,
            'questions' => RunController::SCI_FI_QUESTIONS,
        ])->assertRedirect(route('run.example2'));

        Http::assertSent(fn ($request) => $request['state'] === RunController::SCI_FI_STATE
            && $request['questions']['raise_alert']['type'] === 'noul'
            && $request['questions']['next_move']['type'] === 'choice'
            && $request['questions']['threat_level']['type'] === 'score');
    }

    public function test_third_example_loads_a_delivery_request_and_selects_its_link(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->get('/run/example-3')->assertOk()->assertSee('refrigerated food shipment');

        $this->assertMatchesRegularExpression('/href="[^"]*\/run\/example-3"\s+aria-current="page"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/run\/example"\s+aria-current="page"/', $response->getContent());
        $response->assertSee('value="escalate"', false)->assertSee('value="next_action"', false)->assertSee('value="delay_risk"', false);
    }

    public function test_third_example_can_be_sent_and_keeps_its_page(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('{}')]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->post('/run/example-3', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => RunController::DELIVERY_STATE,
            'questions' => RunController::DELIVERY_QUESTIONS,
        ])->assertRedirect(route('run.example3'));

        Http::assertSent(fn ($request) => $request['state'] === RunController::DELIVERY_STATE
            && $request['questions']['escalate']['type'] === 'noul'
            && $request['questions']['next_action']['type'] === 'choice'
            && $request['questions']['delay_risk']['type'] === 'score');
    }

    public function test_json_requests_return_a_response_panel_without_redirecting(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/events' => Http::response('{"ok":true}', 201)]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->postJson('/run', ['method' => 'POST', 'path' => '/events', 'body' => '{"state":"Hello"}']);

        $response->assertOk()->assertJsonStructure(['html']);
        $this->assertStringContainsString('201', $response->json('html'));
        $this->assertStringContainsString('http://api.example.test/events', $response->json('html'));
        $this->assertStringContainsString('data-response-body', $response->json('html'));
        $this->assertStringNotContainsString('>Response<', $response->json('html'));
        Http::assertSentCount(1);
    }

    public function test_validation_errors_do_not_send_a_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->postJson('/run', ['method' => 'POST', 'path' => '', 'body' => '{}'])
            ->assertUnprocessable()->assertJsonValidationErrors('path');

        Http::assertNothingSent();
    }

    public function test_missing_configuration_returns_a_json_error(): void
    {
        $this->postJson('/run', ['method' => 'GET', 'path' => '/'])
            ->assertUnprocessable()->assertJsonPath('message', 'Save the API URL in Configuration before sending a request.');
    }

    public function test_response_content_is_escaped_in_the_panel(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('</textarea><script>alert(1)</script>', 500)]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->postJson('/run', ['method' => 'GET', 'path' => '/events']);

        $response->assertOk();
        $this->assertStringNotContainsString('<script>', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
        $this->assertStringContainsString('500', $response->json('html'));
    }

    public function test_connection_failures_return_a_rendered_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::failedConnection('API unavailable.')]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->postJson('/run', ['method' => 'GET', 'path' => '/events']);

        $response->assertOk();
        $this->assertStringContainsString('Failed', $response->json('html'));
        $this->assertStringContainsString('API unavailable.', $response->json('html'));
    }

    public function test_api_error_bodies_render_as_a_formatted_error_card(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('{"error": "invalid character \'}\' looking for beginning of object key string"}', 400)]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->postJson('/run', ['method' => 'POST', 'path' => '/v1/systemone', 'body' => '{"state": "Hello"}']);

        $response->assertOk();
        $this->assertStringContainsString('API error', $response->json('html'));
        $this->assertStringContainsString('invalid character &#039;}&#039; looking for beginning of object key string', $response->json('html'));
    }

    public function test_successful_run_renders_duration_question_count_and_token_usage(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response([
            'model' => 'clef-flash',
            'answers' => [
                'urgent' => ['type' => 'noul', 'noul' => 0.9],
            ],
            'usage' => ['input_tokens' => 450, 'output_tokens' => 12],
        ])]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->postJson('/run', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'Support needed.',
            'questions' => [
                ['name' => 'urgent', 'type' => 'noul', 'instructions' => 'Is this urgent?'],
            ],
        ]);

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('200', $html);
        $this->assertStringContainsString('1 question sent', $html);
        $this->assertMatchesRegularExpression('/\b\d+(\.\d+)?\s*(ms|s)\b/', $html);
        $this->assertStringContainsString('450 tokens in · 12 tokens out', $html);
        $this->assertMatchesRegularExpression('/<svg\b[\s\S]*?450 tokens in · 12 tokens out/', $html);
        $this->assertMatchesRegularExpression('/<svg\b[\s\S]*?1 question sent/', $html);
        $this->assertMatchesRegularExpression('/result_view"[^>]*value="form"[\s\S]*?<svg\b[\s\S]*?Easy/', $html);
        $this->assertMatchesRegularExpression('/result_view"[^>]*value="json"[\s\S]*?<svg\b[\s\S]*?JSON/', $html);
    }

    public function test_download_returns_the_request_and_response_json(): void
    {
        Http::preventStrayRequests();

        $response = $this->postJson('/run/download', [
            'request' => '{"state":"Hello","model":"clef-flash"}',
            'response' => '{"ok":true}',
        ]);

        $response->assertDownload('request-response.json');
        $this->assertSame([
            'request' => ['state' => 'Hello', 'model' => 'clef-flash'],
            'response' => ['ok' => true],
        ], json_decode($response->streamedContent(), true));
    }

    public function test_download_keeps_text_that_is_not_json(): void
    {
        Http::preventStrayRequests();

        $response = $this->postJson('/run/download', [
            'request' => 'not json',
            'response' => 'API unavailable.',
        ]);

        $response->assertDownload('request-response.json');
        $this->assertSame([
            'request' => 'not json',
            'response' => 'API unavailable.',
        ], json_decode($response->streamedContent(), true));
    }

    public function test_download_requires_a_response(): void
    {
        $this->postJson('/run/download', [
            'request' => '{"state":"Hello"}',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'response' => 'The response field must be present.',
        ]);
    }

    public function test_native_download_writes_the_file_chosen_in_the_save_dialog(): void
    {
        Http::preventStrayRequests();
        $directory = sys_get_temp_dir().'/clef-airy-download-'.uniqid();
        mkdir($directory);
        $chosen = $directory.'/my-run.json';
        config([
            'nativephp-internal.running' => true,
            'nativephp-internal.api_url' => 'http://native.test',
            'filesystems.disks.downloads' => [
                'driver' => 'local',
                'root' => $directory,
            ],
        ]);
        Http::fake([
            'http://native.test/dialog/save' => Http::response(['result' => $chosen]),
        ]);

        try {
            $this->postJson('/run/download', [
                'request' => '{"state":"Hello"}',
                'response' => '{"ok":true}',
            ])->assertOk()->assertJsonPath('saved', true);

            Http::assertSent(fn ($request): bool => $request->url() === 'http://native.test/dialog/save'
                && $request['title'] === 'Save request and response'
                && $request['buttonLabel'] === 'Save'
                && $request['defaultPath'] === $directory.'/request-response.json'
                && $request['filters'] === [['name' => 'JSON', 'extensions' => ['json']]]);
            $this->assertSame([
                'request' => ['state' => 'Hello'],
                'response' => ['ok' => true],
            ], json_decode((string) file_get_contents($chosen), true));
        } finally {
            @unlink($chosen);
            @rmdir($directory);
        }
    }

    public function test_native_download_writes_nothing_when_the_save_dialog_is_cancelled(): void
    {
        Http::preventStrayRequests();
        $directory = sys_get_temp_dir().'/clef-airy-download-'.uniqid();
        mkdir($directory);
        config([
            'nativephp-internal.running' => true,
            'nativephp-internal.api_url' => 'http://native.test',
            'filesystems.disks.downloads' => [
                'driver' => 'local',
                'root' => $directory,
            ],
        ]);
        Http::fake([
            'http://native.test/dialog/save' => Http::response(['result' => null]),
        ]);

        try {
            $this->postJson('/run/download', [
                'request' => '{"state":"Hello"}',
                'response' => '{"ok":true}',
            ])->assertOk()->assertJsonPath('saved', false);

            $this->assertSame([], array_diff(scandir($directory) ?: [], ['.', '..']));
        } finally {
            @rmdir($directory);
        }
    }

    public function test_native_download_reports_when_the_save_dialog_cannot_open(): void
    {
        Http::preventStrayRequests();
        config([
            'nativephp-internal.running' => true,
            'nativephp-internal.api_url' => 'http://native.test',
        ]);
        Http::fake([
            'http://native.test/*' => Http::failedConnection('Electron is not running.'),
        ]);

        $this->postJson('/run/download', [
            'request' => '{"state":"Hello"}',
            'response' => '{"ok":true}',
        ])->assertServerError()->assertJsonPath('message', 'The save dialog could not be opened.');
    }

    public function test_saved_threshold_is_used_for_yes_no_results(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response([
            'answers' => ['urgent' => ['type' => 'noul', 'noul' => 0.7]],
        ])]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test', 'yes_threshold' => 80]);
        $response = $this->postJson('/run', ['method' => 'POST', 'path' => '/v1/systemone', 'body' => '{"state":"Hello"}']);
        $response->assertOk();
        $this->assertStringContainsString('>No</p>', $response->json('html'));
        $this->assertStringContainsString('70% yes (threshold 80%)', $response->json('html'));
        $this->post('/run', ['method' => 'POST', 'path' => '/v1/systemone', 'body' => '{"state":"Hello"}'])->assertRedirect();
        $this->get('/run')->assertOk()->assertSee('70% yes (threshold 80%)');
    }
}
