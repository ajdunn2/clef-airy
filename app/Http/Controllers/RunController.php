<?php

namespace App\Http\Controllers;

use App\Exceptions\UnreadablePasswordException;
use App\Models\SavedCall;
use App\Models\SystemOneSetting;
use App\Services\SystemOneClient;
use App\Services\SystemOnePayload;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Native\Desktop\Dialog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public const EXAMPLE_STATE = 'Checkout has been failing for every customer for the last hour.';

    public const DELIVERY_STATE = 'A refrigerated food shipment is due at a supermarket distribution centre in four hours. Flooding has closed the usual route. The confirmed open alternate route takes five hours, while the refrigeration unit has fuel for three more hours. A refrigerated depot with space available is 45 minutes away and can arrange onward transport tomorrow morning. Returning to origin takes two hours. The cargo temperature is currently within the required range, and the customer has not yet been notified.';

    /**
     * @var list<array{name: string, type: string, instructions: string, options?: list<array{name: string, description: string}>, levels?: list<string>}>
     */
    public const DELIVERY_QUESTIONS = [
        [
            'name' => 'escalate',
            'type' => 'noul',
            'instructions' => 'Does this delivery disruption require immediate escalation to the dispatch manager and customer?',
        ],
        [
            'name' => 'next_action',
            'type' => 'choice',
            'instructions' => 'Choose the best next action to maintain refrigeration and manage the delivery disruption using the stated travel times and fuel limit.',
            'options' => [
                ['name' => 'alternate_route', 'description' => 'Take the five-hour alternate route toward the distribution centre.'],
                ['name' => 'refrigerated_depot', 'description' => 'Transfer the shipment to the nearby refrigerated depot and arrange onward transport.'],
                ['name' => 'return_to_origin', 'description' => 'Return the shipment to origin for storage and rescheduling.'],
            ],
        ],
        [
            'name' => 'delay_risk',
            'type' => 'score',
            'instructions' => 'How likely is the shipment to miss its delivery deadline?',
            'levels' => ['Unlikely', 'Possible', 'Likely', 'Almost certain'],
        ],
    ];

    public const SCI_FI_STATE = 'The exploration ship Asterion has emerged from hyperspace beside a silent alien megastructure. A repeating signal matches the heartbeat of a crew member who vanished eleven years ago. Three maintenance drones have changed course toward the ship, its shields are at 38%, and the nearest jump point is six minutes away. A lifeboat with two living occupants is drifting inside the structure. The captain must decide whether to attempt a rescue, investigate the signal, or retreat.';

    /**
     * @var list<array{name: string, type: string, instructions: string, true?: string, false?: string, options?: list<array{name: string, description: string}>, levels?: list<string>}>
     */
    public const SCI_FI_QUESTIONS = [
        [
            'name' => 'raise_alert',
            'type' => 'noul',
            'instructions' => 'Should the captain raise red alert based on the available evidence?',
            'true' => 'There is a credible immediate threat to the ship or crew.',
            'false' => 'The situation can safely remain at routine exploration readiness.',
        ],
        [
            'name' => 'next_move',
            'type' => 'choice',
            'instructions' => 'Choose the best next action. Balance rescuing the lifeboat occupants against the risk to the entire crew.',
            'options' => [
                ['name' => 'rescue', 'description' => 'Launch a shielded shuttle to retrieve the lifeboat while the ship holds outside the structure.'],
                ['name' => 'investigate', 'description' => 'Send an unmanned probe to trace the signal and learn why the drones are approaching.'],
                ['name' => 'retreat', 'description' => 'Head for the jump point, broadcast a rescue beacon, and return with reinforcements.'],
            ],
        ],
        [
            'name' => 'threat_level',
            'type' => 'score',
            'instructions' => 'How dangerous is remaining near the megastructure for another six minutes?',
            'levels' => ['Minimal', 'Guarded', 'Severe', 'Existential'],
        ],
    ];

    /**
     * @var list<array{name: string, type: string, instructions: string, true: string, false: string, options: list<array{name: string, description: string}>, levels: list<string>}>
     */
    public const EXAMPLE_QUESTIONS = [
        [
            'name' => 'urgent',
            'type' => 'noul',
            'instructions' => 'Is this support request urgent?',
            'true' => '',
            'false' => '',
            'options' => [],
            'levels' => [],
        ],
        [
            'name' => 'team',
            'type' => 'choice',
            'instructions' => 'Which team should handle this request?',
            'true' => '',
            'false' => '',
            'options' => [
                ['name' => 'billing', 'description' => 'Payments, invoices, and refunds'],
                ['name' => 'technical', 'description' => 'Outages, errors, and configuration'],
                ['name' => 'sales', 'description' => 'Plans and upgrades'],
            ],
            'levels' => [],
        ],
        [
            'name' => 'severity',
            'type' => 'score',
            'instructions' => 'How severe is the customer impact?',
            'true' => '',
            'false' => '',
            'options' => [],
            'levels' => ['No impact', 'Minor', 'Major', 'Critical'],
        ],
    ];

    public const DEFAULT_BODY = <<<'JSON'
        {
          "state": "Checkout has been failing for every customer for the last hour.",
          "questions": {
            "urgent": {"type": "noul", "instructions": "Is this support request urgent?"},
            "team": {
              "type": "choice",
              "instructions": "Which team should handle this request?",
              "criteria": {
                "billing": "Payments, invoices, and refunds",
                "technical": "Outages, errors, and configuration",
                "sales": "Plans and upgrades"
              }
            },
            "severity": {
              "type": "score",
              "instructions": "How severe is the customer impact?",
              "criteria": ["No impact", "Minor", "Major", "Critical"]
            }
          }
        }
        JSON;

    public function create(SystemOneClient $client, SystemOnePayload $payload): View
    {
        return $this->runForm($client, $payload, null);
    }

    public function show(SavedCall $savedCall, SystemOneClient $client, SystemOnePayload $payload): View
    {
        return $this->runForm($client, $payload, $savedCall);
    }

    private function runForm(SystemOneClient $client, SystemOnePayload $payload, ?SavedCall $savedCall): View
    {
        $exampleNumber = match (request()->route()->getName()) {
            'run.example' => 1,
            'run.example2' => 2,
            'run.example3' => 3,
            default => null,
        };
        $isExample = $exampleNumber !== null;
        [$exampleState, $exampleQuestions] = match ($exampleNumber) {
            2 => [self::SCI_FI_STATE, self::SCI_FI_QUESTIONS],
            3 => [self::DELIVERY_STATE, self::DELIVERY_QUESTIONS],
            default => [self::EXAMPLE_STATE, self::EXAMPLE_QUESTIONS],
        };
        $setting = SystemOneSetting::current();
        $selectedModel = $client->modelForApi($setting?->base_url, $savedCall?->model ?: $setting?->model, $setting?->auth_type);
        $models = $client->models($setting?->base_url, $selectedModel, $setting?->auth_type);
        $result = session('result');

        if (is_array($result) && is_string($result['body'] ?? null)) {
            $result['decision'] = $payload->present($result['body'], ($setting?->yes_threshold ?? 50) / 100);
            $result['pretty'] = $payload->pretty($result['body']);
        }

        $defaultBody = in_array($exampleNumber, [2, 3], true) ? $payload->fromForm($exampleState, $exampleQuestions) : trim(self::DEFAULT_BODY);

        $isCloudflare = $client->isCloudflare($setting?->base_url, $setting?->auth_type);
        $defaultPath = $isCloudflare
            ? ($selectedModel === 'typesafe/jev' ? '/' : ($selectedModel ? '/'.ltrim($selectedModel, '/') : ''))
            : '/v1/systemone';

        return view('run.create', [
            'configured' => $setting !== null,
            'isExample' => $isExample,
            'savedCall' => $savedCall,
            'pageTitle' => $savedCall?->name ?? ($isExample ? 'Example #'.$exampleNumber : 'Compose a request'),
            'storeRoute' => match ($exampleNumber) {
                1 => 'run.example.store',
                2 => 'run.example2.store',
                3 => 'run.example3.store',
                default => 'run.store',
            },
            'result' => $result,
            'prefill' => $this->prefill($savedCall, $isExample, $exampleState, $exampleQuestions, $defaultBody, $defaultPath),
            'model' => $selectedModel,
            'models' => $models['models'],
            'visionModels' => $models['vision'],
            'modelError' => $models['error'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $exampleQuestions
     * @return array{body_mode: string, method: string, path: string, state: string, questions: list<array<string, mixed>>, body: string, name: string}
     */
    private function prefill(?SavedCall $savedCall, bool $isExample, string $exampleState, array $exampleQuestions, string $defaultBody, string $defaultPath = '/v1/systemone'): array
    {
        if ($savedCall !== null) {
            $form = $savedCall->body_mode === 'form';
            $questions = $savedCall->questions;

            return [
                'body_mode' => $form ? 'form' : 'json',
                'method' => $savedCall->method,
                'path' => $savedCall->path,
                'state' => $form ? (string) $savedCall->state : '',
                'questions' => $form && is_array($questions) && $questions !== [] ? $questions : [[]],
                'body' => $form ? '' : (string) $savedCall->body,
                'name' => $savedCall->name,
            ];
        }

        return [
            'body_mode' => 'form',
            'method' => 'POST',
            'path' => $defaultPath,
            'state' => $isExample ? $exampleState : '',
            'questions' => $isExample ? $exampleQuestions : [[]],
            'body' => $isExample ? $defaultBody : '',
            'name' => '',
        ];
    }

    public function store(Request $request, SystemOneClient $client, SystemOnePayload $payload): RedirectResponse|JsonResponse
    {
        $setting = SystemOneSetting::current();

        if ($setting === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Save the API URL in Configuration before sending a request.'], 422);
            }

            return redirect()
                ->route('configuration.edit')
                ->with('status', 'Save the API URL before sending a request.');
        }

        $form = $request->input('body_mode') === 'form';
        $rules = [
            'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'path' => ['required', 'string', 'max:2048'],
            'model' => ['nullable', 'string', 'max:255'],
        ];

        if ($form) {
            $rules['state'] = ['required', 'string', 'max:150000'];
            $rules['questions'] = ['nullable', 'array', 'max:64'];
            $rules['questions.*.name'] = ['nullable', 'string', 'max:100'];
            $rules['questions.*.type'] = ['nullable', 'string', 'max:20'];
            $rules['questions.*.instructions'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.true'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.false'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.choice'] = ['nullable', 'string', 'max:20000'];
            $rules['questions.*.score'] = ['nullable', 'string', 'max:20000'];
            $rules['questions.*.options'] = ['nullable', 'array', 'max:255'];
            $rules['questions.*.options.*.name'] = ['nullable', 'string', 'max:200'];
            $rules['questions.*.options.*.description'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.levels'] = ['nullable', 'array', 'max:26'];
            $rules['questions.*.levels.*'] = ['nullable', 'string', 'max:200'];
        } else {
            $rules['body'] = ['nullable', 'string', 'max:150000'];
        }

        $rules['images'] = ['nullable', 'array'];
        $rules['images.*'] = ['file', 'mimes:jpg,jpeg,png,webp'];

        $validated = $request->validate($rules, [
            'method.in' => 'Choose a request method.',
            'path.required' => 'Enter a path.',
            'state.required' => 'Enter the state.',
            'questions.*.levels.max' => 'Score questions allow at most 26 levels.',
            'images.*.mimes' => 'Use a PNG, JPEG, or WebP image.',
        ]);

        $images = $this->encodedImages($request);

        if ($form) {
            try {
                $body = $payload->fromForm($validated['state'], $validated['questions'] ?? [], $images);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'questions' => $exception->getMessage(),
                ]);
            }
        } else {
            $body = $validated['body'] ?? null;

            if ($images !== []) {
                try {
                    $body = $payload->withImages(is_string($body) ? $body : '', $images);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        'images' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($images !== [] && is_string($body) && strlen($body) > 32 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'images' => 'Images must fit in a 32 MB request.',
            ]);
        }

        $model = array_key_exists('model', $validated) ? $validated['model'] : $setting->model;

        if ($setting->model !== $model) {
            $setting->model = $model;
            $setting->save();
        }

        try {
            $response = $client->send(
                $setting,
                $validated['method'],
                $validated['path'],
                $body,
                $model,
            );
        } catch (UnreadablePasswordException $exception) {
            throw ValidationException::withMessages([
                'password' => $exception->getMessage(),
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'path' => $exception->getMessage(),
            ]);
        }

        $duration = $response->formattedDuration();
        $questionsCount = $payload->questionCount($body);

        if ($request->expectsJson()) {
            $result = [
                'url' => $response->url,
                'status' => $response->status,
                'duration' => $duration,
                'questions_count' => $questionsCount,
                'body' => $response->body,
                'decision' => $payload->present($response->body, ($setting->yes_threshold ?? 50) / 100),
                'pretty' => $payload->pretty($response->body),
            ];

            return response()->json(['html' => view('run.response', ['result' => $result])->render()]);
        }

        return redirect()
            ->route(match ($request->route()->getName()) {
                'run.example.store' => 'run.example',
                'run.example2.store' => 'run.example2',
                'run.example3.store' => 'run.example3',
                default => 'run.create',
            })
            ->withInput()
            ->with('result', [
                'url' => $response->url,
                'status' => $response->status,
                'duration' => $duration,
                'questions_count' => $questionsCount,
                'body' => $response->body,
            ]);
    }

    public function download(Request $request, SystemOnePayload $payload, SystemOneClient $client): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'request' => ['nullable', 'string', 'max:500000'],
            'response' => ['present', 'string', 'max:500000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'images.*.mimes' => 'Use a PNG, JPEG, or WebP image.',
        ]);

        $requestBody = $validated['request'] ?? null;
        $images = $this->encodedImages($request);

        if ($images !== []) {
            try {
                $requestBody = $payload->withImages(is_string($requestBody) ? $requestBody : '', $images);
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            if (strlen((string) $requestBody) > 32 * 1024 * 1024) {
                return response()->json(['message' => 'Images must fit in a 32 MB request.'], 422);
            }
        }

        $setting = SystemOneSetting::current();

        if (is_string($requestBody) && $client->isCloudflare($setting?->base_url, $setting?->auth_type)) {
            $decoded = json_decode($requestBody);
            $model = is_object($decoded) ? ($decoded->model ?? $setting?->model) : $setting?->model;

            if ($client->acceptsCloudflareImages(is_string($model) ? $model : null)) {
                $requestBody = $client->embedCloudflareImages($requestBody);
            }
        }

        try {
            $document = json_encode([
                'request' => $this->jsonValue(is_string($requestBody) ? $requestBody : null),
                'response' => $this->jsonValue($validated['response']),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException) {
            return response()->json(['message' => 'The file could not be saved.'], 422);
        }

        if (! config('nativephp-internal.running')) {
            return response()->streamDownload(function () use ($document): void {
                echo $document;
            }, 'request-response.json', ['Content-Type' => 'application/json']);
        }

        try {
            // save() returns the path the user chose. It does not write the file.
            $path = Dialog::new()
                ->title('Save request and response')
                ->button('Save')
                ->defaultPath($this->defaultDownloadPath())
                ->filter('JSON', ['json'])
                ->save();
        } catch (ConnectionException) {
            return response()->json(['message' => 'The save dialog could not be opened.'], 500);
        }

        if (! is_string($path) || $path === '' || str_contains($path, "\0")) {
            return response()->json(['saved' => false]);
        }

        if (is_dir($path) || File::put($path, $document) === false) {
            return response()->json(['message' => 'The file could not be saved.'], 500);
        }

        return response()->json(['saved' => true]);
    }

    /**
     * @return list<string>
     */
    private function encodedImages(Request $request): array
    {
        $encoded = [];

        foreach ($request->file('images', []) as $file) {
            if ($file === null) {
                continue;
            }

            $encoded[] = base64_encode($file->getContent());
        }

        return $encoded;
    }

    private function jsonValue(?string $value): mixed
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        try {
            if (! json_validate($trimmed)) {
                return $value;
            }

            return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
    }

    private function defaultDownloadPath(): string
    {
        $root = config('filesystems.disks.downloads.root');

        if (! is_string($root) || $root === '') {
            return 'request-response.json';
        }

        return Storage::disk('downloads')->path('request-response.json');
    }
}
