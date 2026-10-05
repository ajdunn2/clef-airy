<?php

namespace Tests\Feature;

use App\Models\SystemOneSetting;
use App\Services\SystemOneClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemOneConfigurationTest extends TestCase
{
    public function test_unreachable_ollama_shows_an_error_without_clef_fallback_choices(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://host.containers.internal:11434/api/tags' => Http::failedConnection()]);
        $this->put('/configuration', [
            'api_url' => 'http://host.containers.internal:11434', 'auth_type' => 'none', 'model' => 'clef-flash',
        ]);
        $this->app->bind(SystemOneClient::class, fn () => new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        });

        $this->get(route('configuration.edit'))->assertOk()
            ->assertSee('Could not load models from the API.')
            ->assertSee('Models unavailable')
            ->assertDontSee('value="clef', false);
        $this->get('/run')->assertOk()
            ->assertSee('Could not load models from the API.')
            ->assertDontSee('value="clef', false);
    }

    public function test_ollama_model_choices_do_not_include_an_uninstalled_saved_model(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://host.containers.internal:11434/api/tags' => Http::response([
            'models' => [['name' => 'clef:27b', 'capabilities' => ['decision']]],
        ])]);
        $client = new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        };

        $result = $client->models('http://host.containers.internal:11434', 'clef-flash');

        $this->assertSame(['clef:27b'], $result['models']);
        $this->assertTrue($result['fromApi']);
        $this->assertNull($result['error']);
    }

    public function test_an_empty_ollama_model_list_has_no_fallback_choices(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://host.containers.internal:11434/api/tags' => Http::response(['models' => []])]);
        $client = new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        };

        $result = $client->models('http://host.containers.internal:11434', 'clef-flash');

        $this->assertSame([], $result['models']);
        $this->assertSame('The API returned no available decision models.', $result['error']);
    }

    public function test_rejected_typesafe_keys_show_an_error_instead_of_fallback_model_choices(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.typesafe.ai/v1/models' => Http::response(['detail' => 'Invalid API key'], 401)]);
        $this->put('/configuration', [
            'api_url' => 'https://api.typesafe.ai', 'auth_type' => 'bearer',
            'password' => 'wrong-key', 'model' => 'jev-latest',
        ]);
        $this->app->bind(SystemOneClient::class, fn () => new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        });

        $configuration = $this->get(route('configuration.edit'));
        $workspace = $this->get('/run');

        $configuration->assertOk()->assertSee('The API rejected your credentials.')
            ->assertSee('Models unavailable')
            ->assertDontSee('value="jev-latest"', false)
            ->assertDontSee('value="jev-preview"', false)
            ->assertDontSee('wrong-key');
        $workspace->assertOk()->assertSee('The API rejected your credentials.')
            ->assertDontSee('value="jev-latest"', false)
            ->assertDontSee('value="jev-preview"', false);
        Http::assertSentCount(2);
    }

    public function test_typesafe_connection_failures_do_not_show_fallback_models(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.typesafe.ai/v1/models' => Http::failedConnection()]);
        $this->put('/configuration', [
            'api_url' => 'https://api.typesafe.ai', 'auth_type' => 'bearer', 'password' => 'test-key',
        ]);
        $client = new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        };

        $result = $client->models('https://api.typesafe.ai', 'jev-latest');

        $this->assertSame([], $result['models']);
        $this->assertFalse($result['fromApi']);
        $this->assertStringContainsString('Could not load models from TypeSafe.', $result['error']);
    }

    public function test_switching_from_ollama_to_typesafe_selects_jev_and_removes_clef_choices(): void
    {
        $this->put('/configuration', [
            'api_url' => 'http://localhost:11434', 'model' => 'clef-flash:latest',
        ]);

        $this->put('/configuration', [
            'api_url' => 'https://api.typesafe.ai', 'auth_type' => 'bearer',
            'password' => 'test-api-key', 'model' => 'clef-flash:latest',
        ])->assertRedirect(route('configuration.edit'));

        $this->assertSame('jev-latest', SystemOneSetting::current()->model);
        $this->get(route('configuration.edit'))
            ->assertSee('value="jev-latest" selected', false)
            ->assertDontSee('value="clef', false);
        $this->get('/run')->assertSee('value="jev-latest" selected', false)
            ->assertDontSee('value="clef', false);
    }

    public function test_existing_typesafe_settings_with_a_stale_clef_model_display_jev(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.typesafe.ai', 'auth_type' => 'bearer']);
        $setting = SystemOneSetting::current();
        $setting->model = 'clef-flash:latest';
        $setting->save();

        $this->get(route('configuration.edit'))
            ->assertSee('value="jev-latest" selected', false)
            ->assertDontSee('value="clef', false);
        $this->get('/run')->assertSee('value="jev-latest" selected', false)
            ->assertDontSee('value="clef', false);
    }

    public function test_jev_uses_an_encrypted_bearer_key_and_discovers_models_with_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.typesafe.ai/v1/models' => Http::response(['models' => [['name' => 'jev-latest']]]),
            'https://api.typesafe.ai/v1/systemone' => Http::response('{}'),
        ]);
        $this->put('/configuration', [
            'api_url' => 'https://api.typesafe.ai',
            'auth_type' => 'bearer',
            'password' => 'test-api-key',
            'model' => 'jev-latest',
        ])->assertRedirect(route('configuration.edit'));
        $setting = SystemOneSetting::current();
        $client = new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        };

        $models = $client->models($setting->base_url, $setting->model);
        $client->send($setting, 'POST', '/v1/systemone', '{"state":"Support needed.","questions":{}}', $setting->model);

        $this->assertSame(['models' => ['jev-latest'], 'fromApi' => true, 'error' => null], $models);
        $this->assertNotSame('test-api-key', $setting->getRawOriginal('password'));
        $this->get(route('configuration.edit'))->assertOk()->assertDontSee('test-api-key');
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.typesafe.ai/v1/models'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.typesafe.ai/v1/systemone'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['model'] === 'jev-latest');
    }

    public function test_no_authentication_does_not_send_saved_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->put('/configuration', [
            'api_url' => 'http://api.example.test',
            'auth_type' => 'none',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $this->postJson('/run', ['method' => 'GET', 'path' => '/events'])->assertOk();

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_model_selection_preserves_json_objects_and_large_numbers(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->postJson('/run', [
            'method' => 'POST', 'path' => '/v1/systemone', 'model' => 'jev-latest',
            'body' => '{"model":"old","state":{"id":9007199254740993,"empty":{},"model":"nested, model"},"questions":{"0":{"type":"noul","instructions":"Check coverage."}}}',
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();
            $decoded = json_decode($body);

            return str_contains($body, '9007199254740993')
                && $decoded->state->empty instanceof \stdClass
                && $decoded->state->model === 'nested, model'
                && $decoded->questions instanceof \stdClass
                && $decoded->model === 'jev-latest'
                && substr_count($body, '"model":') === 2;
        });
    }

    public function test_settings_are_saved_and_the_password_is_not_shown_again(): void
    {
        $response = $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $response
            ->assertRedirect(route('configuration.edit'))
            ->assertSessionHas('status', 'Saved.');

        $this->assertDatabaseHas('system_one_settings', [
            'base_url' => 'https://api.example.test',
            'username' => 'ada',
        ]);
        $this->assertDatabaseMissing('system_one_settings', [
            'password' => 'secret',
        ]);
        $this->assertSame('secret', SystemOneSetting::current()?->password);

        $this->get('/')
            ->assertSee('https://api.example.test')
            ->assertSee('ada')
            ->assertSee('placeholder="********"', false)
            ->assertSee('A password is saved. Leave this blank to keep it.')
            ->assertDontSee('secret');
    }

    public function test_a_blank_password_keeps_the_saved_password(): void
    {
        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $response = $this->put('/configuration', [
            'api_url' => 'https://api.example.test/v2',
            'username' => 'ada',
            'password' => '',
        ]);

        $response->assertRedirect(route('configuration.edit'));
        $this->assertSame('https://api.example.test/v2', SystemOneSetting::current()?->base_url);
        $this->assertSame('secret', SystemOneSetting::current()?->password);
    }

    public function test_configuration_requires_an_http_url(): void
    {
        $response = $this->from('/')->put('/configuration', [
            'api_url' => 'ftp://files.example.test',
            'username' => '',
            'password' => '',
        ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'api_url' => 'Enter an http or https API URL.',
            ]);
        $this->assertDatabaseCount('system_one_settings', 0);
    }

    public function test_ollama_can_be_saved_without_a_username_or_password(): void
    {
        $response = $this->put('/configuration', [
            'api_url' => 'http://localhost:11434',
            'username' => '',
            'password' => '',
        ]);

        $response->assertRedirect(route('configuration.edit'));
        $this->assertSame('http://localhost:11434', SystemOneSetting::current()?->base_url);
        $this->assertSame('', SystemOneSetting::current()?->username);
        $this->assertSame('', SystemOneSetting::current()?->password);

        $this->get('/run/example')
            ->assertSee('/v1/systemone')
            ->assertSee('clef-flash')
            ->assertSee('clef')
            ->assertSee('"urgent"')
            ->assertSee('"criteria"')
            ->assertDontSee('"model"');
    }

    public function test_credentials_in_the_api_url_are_rejected(): void
    {
        $response = $this->from('/')->put('/configuration', [
            'api_url' => 'https://ada:secret@api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'api_url' => 'Put the username and password in their own fields.',
            ]);
        $this->assertDatabaseCount('system_one_settings', 0);
    }

    public function test_run_page_asks_for_configuration_before_a_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->get('/run')
            ->assertOk()
            ->assertSee('Save the API URL before sending a request.');

        $this->post('/run', [
            'method' => 'GET',
            'path' => '/',
        ])->assertRedirect(route('configuration.edit'));

        Http::assertNothingSent();
    }

    public function test_run_shows_the_response_from_the_saved_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.example.test/events' => Http::response('{"ok":true}', 201),
        ]);

        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $response = $this->post('/run', [
            'method' => 'GET',
            'path' => '/events',
        ]);

        $response->assertRedirect(route('run.create'));

        $this->get('/run')
            ->assertSee('https://api.example.test/events')
            ->assertSee('201')
            ->assertSee('"ok"');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.example.test/events'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('ada:secret'));
        });
    }

    public function test_choice_options_and_score_levels_are_separate_fields(): void
    {
        $this->put('/configuration', [
            'api_url' => 'http://localhost:11434',
            'username' => '',
            'password' => '',
        ]);

        $this->get('/run/example')
            ->assertOk()
            ->assertSee('name="questions[1][options][0][name]"', false)
            ->assertSee('value="billing"', false)
            ->assertSee('name="questions[1][options][0][description]"', false)
            ->assertSee('Payments, invoices, and refunds', false)
            ->assertSee('name="questions[2][levels][0]"', false)
            ->assertSee('value="No impact"', false)
            ->assertSee('data-run', false)
            ->assertSee('data-send', false)
            ->assertSee('aria-label="Add option"', false)
            ->assertSee('aria-label="Remove option"', false)
            ->assertDontSee('One option per line', false);
    }

    public function test_the_form_builder_sends_questions_and_shows_plain_answers(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:11434/v1/systemone' => Http::response(json_encode([
                'model' => 'clef-flash',
                'answers' => [
                    'urgent' => ['type' => 'noul', 'noul' => 0.95],
                    'team' => [
                        'type' => 'choice',
                        'choice' => 'technical',
                        'probabilities' => ['billing' => 0.05, 'technical' => 0.95],
                        'confidence' => 0.8,
                    ],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
            ]), 200),
        ]);

        $this->put('/configuration', [
            'api_url' => 'http://localhost:11434',
            'username' => '',
            'password' => '',
        ]);

        $this->post('/run', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'model' => 'clef-flash',
            'body_mode' => 'form',
            'state' => 'Checkout is down.',
            'questions' => [
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
            ],
        ])->assertRedirect(route('run.create'));

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && ($body['state'] ?? null) === 'Checkout is down.'
                && ($body['questions']['urgent']['type'] ?? null) === 'noul'
                && ($body['questions']['team']['criteria']['technical'] ?? null) === 'Outages'
                && ($body['model'] ?? null) === 'clef-flash';
        });

        $this->get('/run')
            ->assertSee('Yes')
            ->assertSee('95% yes')
            ->assertSee('technical')
            ->assertSee('Confidence 80%')
            ->assertSee('JSON');
    }

    public function test_a_request_without_credentials_omits_basic_auth(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:11434/v1/systemone' => Http::response('{"model":"clef-flash"}', 200),
        ]);

        $this->put('/configuration', [
            'api_url' => 'http://localhost:11434',
            'username' => '',
            'password' => '',
        ]);

        $this->post('/run', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'model' => 'clef',
            'body' => '{"state":"Hello"}',
        ])->assertRedirect(route('run.create'));

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'http://localhost:11434/v1/systemone'
                && is_array($body)
                && ($body['model'] ?? null) === 'clef'
                && ($body['state'] ?? null) === 'Hello'
                && ! $request->hasHeader('Authorization');
        });
        $this->assertSame('clef', SystemOneSetting::current()?->model);
    }

    public function test_a_json_body_is_sent_as_json(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.example.test/events' => Http::response('created', 201),
        ]);

        $this->put('/configuration', [
            'api_url' => 'https://api.example.test/',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $this->post('/run', [
            'method' => 'POST',
            'path' => 'events',
            'body' => '{"name":"Ada"}',
        ]);

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://api.example.test/events'
                && is_array($body)
                && ($body['name'] ?? null) === 'Ada'
                && ($body['model'] ?? null) === 'clef-flash'
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_a_path_on_another_host_is_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $response = $this->from('/run')->post('/run', [
            'method' => 'GET',
            'path' => 'https://evil.test/steal',
        ]);

        $response
            ->assertRedirect('/run')
            ->assertSessionHasErrors([
                'path' => 'Enter a path on the saved API.',
            ]);
        Http::assertNothingSent();
    }

    public function test_a_connection_failure_is_shown_as_the_response(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.example.test/*' => Http::failedConnection('Could not reach System One.'),
        ]);

        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'username' => 'ada',
            'password' => 'secret',
        ]);

        $this->post('/run', [
            'method' => 'GET',
            'path' => '/',
        ])->assertRedirect(route('run.create'));

        $this->get('/run')
            ->assertSee('Failed')
            ->assertSee('Could not reach System One.');
    }

    public function test_model_lookup_uses_only_the_saved_api_url(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'clef-flash:latest', 'capabilities' => ['decision']],
                ],
            ]),
        ]);

        $client = new class extends SystemOneClient
        {
            protected function shouldLookupModels(): bool
            {
                return true;
            }
        };

        $models = $client->models('http://localhost:11434');

        Http::assertSentCount(1);
        $this->assertTrue($models['fromApi']);
        $this->assertSame(['clef-flash:latest'], $models['models']);
    }
}
