<?php

namespace Tests\Feature;

use App\Http\Controllers\RunController;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWorkspaceTest extends TestCase
{
    public function test_run_starts_blank_with_its_sidebar_link_selected(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->get('/run')->assertOk()->assertSee('Example #1')->assertSee('Example #2');

        $this->assertMatchesRegularExpression('/<textarea[^>]*id="state"[^>]*><\/textarea>/', $response->getContent());
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="body"[^>]*><\/textarea>/', $response->getContent());
        $this->assertMatchesRegularExpression('/href="[^"]*\/run"\s+aria-current="page"/', $response->getContent());
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
}
