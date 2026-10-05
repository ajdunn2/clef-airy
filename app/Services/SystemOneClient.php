<?php

namespace App\Services;

use App\Models\SystemOneSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SystemOneClient
{
    /**
     * @return array{models: list<string>, fromApi: bool, error: ?string}
     */
    public function models(?string $baseUrl, ?string $selected = null): array
    {
        try {
            $installed = $this->installedModels($baseUrl);
        } catch (InvalidArgumentException $exception) {
            return ['models' => [], 'fromApi' => false, 'error' => $exception->getMessage()];
        }
        $names = $installed ?? ($this->isTypeSafe($baseUrl) ? ['jev-latest', 'jev-preview'] : ['clef-flash', 'clef']);
        $selected = filled($selected) ? $this->modelForApi($baseUrl, $selected) : null;

        if (filled($selected) && ! in_array($selected, $names, true) && ($installed === null || $this->isTypeSafe($baseUrl))) {
            array_unshift($names, $selected);
        }

        return [
            'models' => array_values(array_unique($names)),
            'fromApi' => $installed !== null,
            'error' => null,
        ];
    }

    public function modelForApi(?string $baseUrl, ?string $selected): string
    {
        if ($this->isTypeSafe($baseUrl) && (! filled($selected) || str_starts_with($selected, 'clef'))) {
            return 'jev-latest';
        }

        return filled($selected) ? $selected : ($this->isTypeSafe($baseUrl) ? 'jev-latest' : 'clef-flash');
    }

    private function isTypeSafe(?string $baseUrl): bool
    {
        return strtolower((string) parse_url($baseUrl ?? '', PHP_URL_HOST)) === 'api.typesafe.ai';
    }

    public function send(SystemOneSetting $setting, string $method, string $path, ?string $body, ?string $model = null): SystemOneResponse
    {
        if ($setting->auth_type !== 'none' && $setting->passwordNeedsReset()) {
            throw new InvalidArgumentException('Re-enter your API password in Configuration before sending a request.');
        }

        $url = $this->endpoint($setting->base_url, $path);
        $pending = Http::connectTimeout(5)
            ->timeout(120)
            ->acceptJson();

        $pending = $this->authenticate($pending, $setting);

        if (filled($model) && (filled($body) || in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true))) {
            $body = $this->withModel($body, $model);
        }

        if (filled($body)) {
            $contentType = json_validate($body) ? 'application/json' : 'text/plain';
            $pending = $pending->withBody($body, $contentType);
        }

        try {
            $response = $pending->send(strtoupper($method), $url);
        } catch (ConnectionException $exception) {
            return new SystemOneResponse($url, null, $exception->getMessage());
        }

        return new SystemOneResponse($url, $response->status(), $this->limit($response->body()));
    }

    /**
     * @return list<string>|null
     */
    private function installedModels(?string $baseUrl): ?array
    {
        if (! filled($baseUrl) || ! $this->shouldLookupModels()) {
            return null;
        }

        try {
            $setting = SystemOneSetting::current();
            $setting = $setting?->base_url === $baseUrl ? $setting : null;
            $path = $this->isTypeSafe($baseUrl) || $setting?->auth_type === 'bearer' ? '/v1/models' : '/api/tags';
            $response = $this->getQuietly($this->endpoint($baseUrl, $path), $setting);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('Could not load models. Check the API URL in Configuration.', previous: $exception);
        }

        if ($response !== null && in_array($response->status(), [401, 403], true)) {
            throw new InvalidArgumentException('The API rejected your credentials. Check the authentication type and password / API key in Configuration.');
        }

        if ($response === null || ! $response->successful() || ! is_array($response->json('models'))) {
            if ($this->isTypeSafe($baseUrl)) {
                throw new InvalidArgumentException("Could not load models from TypeSafe.\nCheck the API URL and connection, then try again.");
            }

            throw new InvalidArgumentException("Could not load models from the API.\nCheck the API URL and connection, then try again.");
        }

        $names = [];

        foreach ($response->json('models') as $model) {
            if (! is_array($model)) {
                continue;
            }

            $capabilities = $model['capabilities'] ?? [];
            $name = $model['name'] ?? $model['model'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            if (is_array($capabilities) && $capabilities !== [] && ! in_array('decision', $capabilities, true)) {
                continue;
            }

            $names[] = $name;
        }

        if ($names === []) {
            throw new InvalidArgumentException('The API returned no available decision models.');
        }

        return array_values(array_unique($names));
    }

    protected function shouldLookupModels(): bool
    {
        return ! app()->runningUnitTests();
    }

    private function authenticate(PendingRequest $pending, ?SystemOneSetting $setting): PendingRequest
    {
        if ($setting?->auth_type === 'bearer' && filled($setting->password)) {
            return $pending->withToken($setting->password);
        }

        if ($setting !== null && $setting->auth_type !== 'none' && $setting->auth_type !== 'bearer'
            && (filled($setting->username) || filled($setting->password))) {
            return $pending->withBasicAuth((string) $setting->username, (string) $setting->password);
        }

        return $pending;
    }

    private function getQuietly(string $url, ?SystemOneSetting $setting): ?Response
    {
        try {
            return $this->authenticate(Http::connectTimeout(2)->timeout(3)->acceptJson(), $setting)->get($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function withModel(?string $body, string $model): string
    {
        if (! filled($body)) {
            return json_encode(['model' => $model], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! json_validate($body)) {
            return $body;
        }

        $decoded = json_decode($body);

        if (! is_object($decoded)) {
            return $body;
        }

        $content = substr(trim($body), 1, -1);
        $properties = [];
        $start = 0;
        $depth = 0;
        $quoted = false;
        $escaped = false;

        for ($index = 0; $index < strlen($content); $index++) {
            $character = $content[$index];

            if ($quoted) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $quoted = false;
                }

                continue;
            }

            if ($character === '"') {
                $quoted = true;
            } elseif ($character === '{' || $character === '[') {
                $depth++;
            } elseif ($character === '}' || $character === ']') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $properties[] = substr($content, $start, $index - $start);
                $start = $index + 1;
            }
        }

        if (trim($content) !== '') {
            $properties[] = substr($content, $start);
        }

        $properties = array_filter($properties, function (string $property): bool {
            $value = json_decode('{'.$property.'}');

            return ! property_exists($value, 'model');
        });
        $properties[] = '"model":'.json_encode($model, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '{'.implode(',', $properties).'}';
    }

    private function endpoint(string $baseUrl, string $path): string
    {
        $path = trim($path);

        if ($path === '' || str_contains($path, '://') || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Enter a path on the saved API.');
        }

        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        if (! is_string($baseHost) || $baseHost !== $targetHost) {
            throw new InvalidArgumentException('Enter a path on the saved API.');
        }

        return $url;
    }

    private function limit(string $body): string
    {
        $limit = 100_000;

        if (mb_strlen($body) <= $limit) {
            return $body;
        }

        return mb_substr($body, 0, $limit)."\n\n[truncated]";
    }
}
