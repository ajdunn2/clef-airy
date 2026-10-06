<?php

namespace App\Services;

use App\Exceptions\UnreadablePasswordException;
use App\Models\SystemOneSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SystemOneClient
{
    /**
     * @var list<string>
     */
    public const CLOUDFLARE_MODELS = [
        '@cf/cloudflare/clef-flash',
        '@cf/cloudflare/clef',
        'typesafe/jev',
    ];

    /**
     * @return array{models: list<string>, fromApi: bool, error: ?string}
     */
    public function models(?string $baseUrl, ?string $selected = null, ?string $authType = null): array
    {
        if (! filled($baseUrl) && $authType !== 'cloudflare') {
            return ['models' => [], 'fromApi' => false, 'error' => null];
        }

        if ($this->isCloudflare($baseUrl, $authType)) {
            return ['models' => self::CLOUDFLARE_MODELS, 'fromApi' => false, 'error' => null];
        }

        try {
            $installed = $this->installedModels($baseUrl);
        } catch (InvalidArgumentException $exception) {
            return ['models' => [], 'fromApi' => false, 'error' => $exception->getMessage()];
        }
        $names = $installed ?? ($this->isTypeSafe($baseUrl) ? ['jev-latest', 'jev-preview'] : ['clef-flash', 'clef']);
        $selected = filled($selected) ? $this->modelForApi($baseUrl, $selected, $authType) : null;

        if (filled($selected) && ! in_array($selected, $names, true) && $installed === null) {
            array_unshift($names, $selected);
        }

        return [
            'models' => array_values(array_unique($names)),
            'fromApi' => $installed !== null,
            'error' => null,
        ];
    }

    public function modelForApi(?string $baseUrl, ?string $selected, ?string $authType = null): ?string
    {
        if (! filled($selected) && $this->isCloudflare($baseUrl, $authType)
            && preg_match('~/ai/run/((?:@cf/|typesafe/)[^?#]+)~', $baseUrl ?? '', $matches)) {
            $selected = rtrim($matches[1], '/');
        }

        $selected = $selected === '@cf/typesafe/jev' ? 'typesafe/jev' : $selected;

        if (! filled($selected)) {
            return null;
        }

        if ($this->isTypeSafe($baseUrl) && ! str_starts_with($selected, 'jev')) {
            return null;
        }

        if ($this->isCloudflare($baseUrl, $authType) && ! str_starts_with($selected, '@cf/') && $selected !== 'typesafe/jev') {
            return null;
        }

        if (! $this->isCloudflare($baseUrl, $authType) && (str_starts_with($selected, '@cf/') || $selected === 'typesafe/jev')) {
            return null;
        }

        return $selected;
    }

    public function isCloudflare(?string $baseUrl = null, ?string $authType = null): bool
    {
        if ($authType === 'cloudflare') {
            return true;
        }

        $host = strtolower((string) parse_url($baseUrl ?? '', PHP_URL_HOST));

        return $host === 'api.cloudflare.com';
    }

    public function isTypeSafe(?string $baseUrl): bool
    {
        return strtolower((string) parse_url($baseUrl ?? '', PHP_URL_HOST)) === 'api.typesafe.ai';
    }

    public function send(SystemOneSetting $setting, string $method, string $path, ?string $body, ?string $model = null): SystemOneResponse
    {
        if ($setting->auth_type !== 'none' && $setting->passwordNeedsReset()) {
            throw new UnreadablePasswordException;
        }

        $baseUrl = $setting->base_url;
        if ($this->isCloudflare($baseUrl, $setting->auth_type)) {
            $model = $this->modelForApi($baseUrl, $model, $setting->auth_type);
            $baseUrl = preg_replace('~(/ai/run)/(?:@cf/|typesafe/)[^?#]+~', '$1', $baseUrl);
        }

        if ($this->isCloudflare($baseUrl, $setting->auth_type) && filled($model)) {
            if ($path === '' || $path === '/v1/systemone' || str_starts_with($path, '/@cf/')) {
                $path = '/'.ltrim($model, '/');
            }
        }

        $isPartnerModel = $this->isCloudflare($baseUrl, $setting->auth_type) && $model === 'typesafe/jev';
        $url = $this->endpoint($baseUrl, $isPartnerModel ? '/' : $path);
        if ($isPartnerModel) {
            $url = rtrim($url, '/');
        }
        $pending = Http::connectTimeout(5)
            ->timeout(120)
            ->acceptJson();

        $pending = $this->authenticate($pending, $setting);

        if ($isPartnerModel && filled($body) && json_validate($body)) {
            $body = '{"model":"typesafe/jev","input":'.$body.'}';
        } elseif (filled($model) && (filled($body) || in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true))) {
            $body = $this->withModel($body, $model);
        }

        if (filled($body)) {
            $contentType = json_validate($body) ? 'application/json' : 'text/plain';
            $pending = $pending->withBody($body, $contentType);
        }

        $startedAt = microtime(true);

        try {
            $response = $pending->send(strtoupper($method), $url);
            $duration = microtime(true) - $startedAt;
        } catch (ConnectionException $exception) {
            $duration = microtime(true) - $startedAt;

            return new SystemOneResponse($url, null, $exception->getMessage(), $duration);
        }

        return new SystemOneResponse($url, $response->status(), $this->limit($response->body()), $duration);
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
        if (in_array($setting?->auth_type, ['bearer', 'cloudflare'], true) && filled($setting->password)) {
            return $pending->withToken($setting->password);
        }

        if ($setting !== null && ! in_array($setting->auth_type, ['none', 'bearer', 'cloudflare'], true)
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
            return json_encode(['model' => $this->normalizeModelForPayload($model)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        $properties[] = '"model":'.json_encode($this->normalizeModelForPayload($model), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '{'.implode(',', $properties).'}';
    }

    public function normalizeModelForPayload(string $model): string
    {
        return str_starts_with($model, '@cf/') ? basename($model) : $model;
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
        $limit = 150_000;

        if (mb_strlen($body) <= $limit) {
            return $body;
        }

        return mb_substr($body, 0, $limit)."\n\n[truncated]";
    }
}
