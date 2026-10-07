<?php

namespace Tests\Feature;

use App\Models\SavedCall;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SavedCallTest extends TestCase
{
    public function test_bookmarking_an_open_bookmark_creates_a_new_call_without_changing_the_original(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);
        $original = SavedCall::factory()->create([
            'name' => 'Night desk',
            'body_mode' => 'form',
            'state' => 'The night desk is uncovered.',
        ]);
        $originalAttributes = $original->fresh()->getAttributes();
        $page = $this->get(route('calls.show', $original));
        $page->assertSee('formaction="'.route('calls.store').'"', false);
        preg_match('/formaction="([^"]+)"/', $page->getContent(), $matches);

        $response = $this->from(route('calls.show', $original))->post($matches[1], [
            'name' => 'Night desk',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'The night desk is now covered.',
            'questions' => [
                ['name' => 'urgent', 'type' => 'noul', 'instructions' => 'Is it urgent?'],
            ],
        ]);

        $copy = SavedCall::query()->whereKeyNot($original->id)->sole();
        $response->assertRedirect(route('calls.show', $copy));
        $this->assertDatabaseCount('saved_calls', 2);
        $this->assertSame($originalAttributes, $original->fresh()->getAttributes());
        $this->assertSame('Night desk', $copy->name);
        $this->assertSame('The night desk is now covered.', $copy->state);
        $this->assertSame('urgent', $copy->questions[0]['name']);
    }

    public function test_saving_a_form_call_adds_it_to_the_menu_and_reopens_it(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $response = $this->post('/calls', [
            'name' => 'Night desk',
            'method' => 'PATCH',
            'path' => '/v1/night',
            'model' => 'clef',
            'body_mode' => 'form',
            'state' => 'The night desk is uncovered.',
            'questions' => [
                [
                    'name' => 'team',
                    'type' => 'choice',
                    'instructions' => 'Which team?',
                    'true' => '',
                    'false' => '',
                    'options' => [
                        ['name' => 'billing', 'description' => 'Payments', 'extra' => 'nope'],
                    ],
                    'levels' => ['Minor', 'Major'],
                    'injected' => 'nope',
                ],
            ],
        ]);

        $response->assertRedirect();
        $call = SavedCall::query()->sole();
        $this->assertSame('Night desk', $call->name);
        $this->assertSame('PATCH', $call->method);
        $this->assertSame('/v1/night', $call->path);
        $this->assertSame('clef', $call->model);
        $this->assertSame('form', $call->body_mode);
        $this->assertSame('The night desk is uncovered.', $call->state);
        $this->assertNull($call->body);
        $this->assertSame([
            [
                'name' => 'team',
                'type' => 'choice',
                'instructions' => 'Which team?',
                'true' => null,
                'false' => null,
                'options' => [
                    ['name' => 'billing', 'description' => 'Payments'],
                ],
                'levels' => ['Minor', 'Major'],
            ],
        ], $call->questions);

        $page = $this->get(route('calls.show', $call));

        $page->assertOk()
            ->assertSee('Bookmarked.')
            ->assertSee('The night desk is uncovered.')
            ->assertSee('value="billing"', false)
            ->assertSee('x-data="runWorkspace"', false)
            ->assertDontSee('max-w-2xl', false)
            ->assertDontSee('Call name');
        $this->assertMatchesRegularExpression('/Request<\/span>\s*<button[^>]*>\s*<svg\b[\s\S]*?M17 3a2 2 0 0 1 2 2v15[\s\S]*?<\/svg>\s*Bookmark<\/button>/', $page->getContent());
        $this->assertMatchesRegularExpression('/<dialog\b[\s\S]*id="bookmark-name"[\s\S]*form="run-form"/', $page->getContent());
        $this->assertMatchesRegularExpression('/<span class="min-w-0 flex-1 truncate">Night desk<\/span>\s*<\/a>\s*<button\s+type="button"[^>]*aria-label="Remove Night desk"[^>]*>\s*<svg\b/', $page->getContent());
        $page->assertSee('M10.586 21.414', false)
            ->assertSee('M19 17V5a2 2 0 0 0-2-2H4', false)
            ->assertSee('m6 20 .7-2.9A1.4 1.4 0 0 1 8.1 16h7.8', false)
            ->assertDontSee('M5 5a2 2 0 0 1 3.008-1.728', false);
        $page->assertSee("askRemove('Night desk', 'remove-bookmark-{$call->id}')", false);
        $page->assertSee('aria-labelledby="remove-bookmark-title"', false)
            ->assertSee(':form="pendingForm"', false)
            ->assertSee('id="remove-bookmark-'.$call->id.'"', false)
            ->assertSee('>Remove</button>', false)
            ->assertDontSee('form="remove-call"', false)
            ->assertDontSee('form="remove-bookmark-', false);
        $this->assertMatchesRegularExpression('/<option value="PATCH" selected>/', $page->getContent());
        $this->assertMatchesRegularExpression('/value="\/v1\/night"/', $page->getContent());
        $this->assertMatchesRegularExpression('/href="[^"]*\/calls\/'.$call->id.'"[^>]*aria-current="page"/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/run"\s+aria-current="page"/', $page->getContent());

        $this->get('/')->assertSee('Night desk')->assertSee('max-w-2xl', false);
        $this->get('/run')->assertSee('Night desk')->assertDontSee('The night desk is uncovered.')->assertDontSee('max-w-2xl', false);
    }

    public function test_saving_a_json_call_reopens_the_json_body(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->post('/calls', [
            'name' => 'Raw note',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'json',
            'body' => '{"state":"Bring the log."}',
            'state' => 'This form state should not be stored.',
            'questions' => [
                ['name' => 'should-not-restore', 'type' => 'noul', 'instructions' => 'Hidden'],
            ],
        ])->assertRedirect();

        $call = SavedCall::query()->sole();
        $this->assertSame('json', $call->body_mode);
        $this->assertSame('{"state":"Bring the log."}', $call->body);
        $this->assertNull($call->state);
        $this->assertNull($call->questions);

        $page = $this->get(route('calls.show', $call))->assertOk()->assertSee('{"state":"Bring the log."}');

        $this->assertMatchesRegularExpression('/name="body_mode"[^>]*value="json"[^>]*checked/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="body_mode"[^>]*value="form"[^>]*checked/', $page->getContent());
        $page->assertDontSee('value="should-not-restore"', false);
        $page->assertDontSee('This form state should not be stored.');
    }

    public function test_saving_an_open_call_updates_it(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);
        $call = SavedCall::factory()->create(['name' => 'Night desk']);

        $this->from(route('calls.show', $call))->post(route('calls.update', $call), [
            'name' => 'Refunds',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'model' => 'clef',
            'body_mode' => 'form',
            'state' => 'Refunds are late.',
            'questions' => [
                [
                    'name' => 'urgent',
                    'type' => 'noul',
                    'instructions' => 'Is it urgent?',
                ],
            ],
        ])->assertRedirect(route('calls.show', $call));

        $call->refresh();
        $this->assertSame('Refunds', $call->name);
        $this->assertSame('Refunds are late.', $call->state);
        $this->assertSame('urgent', $call->questions[0]['name']);
        $this->assertDatabaseCount('saved_calls', 1);
        $this->get('/run')->assertSee('Refunds')->assertDontSee('Night desk');
    }

    public function test_removing_a_call_takes_it_out_of_the_menu(): void
    {
        $call = SavedCall::factory()->create(['name' => 'Night desk']);

        $this->delete(route('calls.destroy', $call))->assertRedirect(route('run.create'));

        $this->assertModelMissing($call);
        $this->get('/run')->assertDontSee('Night desk')->assertSee('Removed.');
    }

    public function test_a_bookmark_with_27_score_levels_is_rejected(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->followingRedirects()->from('/run')->post('/calls', [
            'name' => 'Wide rubric',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'Support needed.',
            'questions' => [[
                'name' => 'severity',
                'type' => 'score',
                'instructions' => 'How severe?',
                'levels' => array_map(fn (int $index): string => 'Level '.$index, range(1, 27)),
            ]],
        ])->assertOk()->assertSee('Score questions allow at most 26 levels.');

        $this->assertDatabaseCount('saved_calls', 0);
    }

    public function test_a_call_without_a_name_is_rejected(): void
    {
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->followingRedirects()->from('/run')->post('/calls', [
            'name' => '',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
        ])->assertOk()
            ->assertSee('Enter a name for this bookmark.')
            ->assertSee('<dialog', false)
            ->assertSee('data-open', false);

        $this->assertDatabaseCount('saved_calls', 0);
    }

    public function test_a_request_can_be_bookmarked_before_or_after_it_is_sent(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response('{"ok":true}')]);
        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $before = $this->get('/run')->assertOk()->assertDontSee('data-response-body', false);
        $this->assertMatchesRegularExpression('/Request<\/span>\s*<button[^>]*>\s*<svg\b[\s\S]*?M17 3a2 2 0 0 1 2 2v15[\s\S]*?<\/svg>\s*Bookmark<\/button>\s*<div[^>]*>\s*<label[^>]*>\s*<input[^>]*name="body_mode"/', $before->getContent());
        $this->assertMatchesRegularExpression('/<dialog\b[\s\S]*id="bookmark-name"/', $before->getContent());

        $after = $this->followingRedirects()->post('/run', [
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'The desk is quiet.',
            'questions' => [
                ['name' => 'urgent', 'type' => 'noul', 'instructions' => 'Is it urgent?'],
            ],
        ])->assertOk()->assertSee('{"ok":true}');
        $this->assertMatchesRegularExpression('/Request<\/span>\s*<button[^>]*>\s*<svg\b[\s\S]*?M17 3a2 2 0 0 1 2 2v15[\s\S]*?<\/svg>\s*Bookmark<\/button>/', $after->getContent());

        $this->post('/calls', [
            'name' => 'After the run',
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
            'state' => 'The desk is quiet.',
            'questions' => [
                ['name' => 'urgent', 'type' => 'noul', 'instructions' => 'Is it urgent?'],
            ],
        ])->assertRedirect();

        $call = SavedCall::query()->sole();
        $this->assertSame('After the run', $call->name);
        $this->assertSame('The desk is quiet.', $call->state);
    }

    public function test_a_call_without_a_body_mode_is_rejected(): void
    {
        $this->from('/run')->post('/calls', [
            'name' => 'Night desk',
            'method' => 'POST',
            'path' => '/v1/systemone',
        ])->assertRedirect('/run')->assertSessionHasErrors('body_mode');

        $this->assertDatabaseCount('saved_calls', 0);
    }

    public function test_a_call_name_is_escaped_in_the_menu(): void
    {
        $name = '"><script>alert(1)</script>';

        $this->post('/calls', [
            'name' => $name,
            'method' => 'POST',
            'path' => '/v1/systemone',
            'body_mode' => 'form',
        ])->assertRedirect();

        $this->get('/')
            ->assertSee($name)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
