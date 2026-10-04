<?php

namespace App\Services;

use App\Models\SystemOneSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SystemOneClient
{
    /**
     * @return array{models: list<string>, fromApi: bool}
     */
    public function models(?string $baseUrl, ?string $selected = null): array
    {
        $installed = $this->installedModels($baseUrl);
        $names = $installed ?? ['clef-flash', 'clef'];

        if (filled($selected) && ! in_array($selected, $names, true)) {
            array_unshift($names, $selected);
        }

        return [
            'models' => array_values(array_unique($names)),
            'fromApi' => $installed !== null,
        ];
    }

    public function send(SystemOneSetting $setting, string $method, string $path, ?string $body, ?string $model = null): SystemOneResponse
    {
        if ($setting->passwordNeedsReset()) {
            throw new InvalidArgumentException('Re-enter your API password in Configuration before sending a request.');
        }

        $url = $this->endpoint($setting->base_url, $path);
        $pending = Http::connectTimeout(5)
            ->timeout(120)
            ->acceptJson();

        if (filled($setting->username) || filled($setting->password)) {
            $pending = $pending->withBasicAuth((string) $setting->username, (string) $setting->password);
        }

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
            $response = $this->getQuietly($this->endpoint($baseUrl, '/api/tags'));
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($response === null || ! $response->successful() || ! is_array($response->json('models'))) {
            return null;
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

        return $names === [] ? null : array_values(array_unique($names));
    }

    protected function shouldLookupModels(): bool
    {
        return ! app()->runningUnitTests();
    }

    private function getQuietly(string $url): ?Response
    {
        try {
            return Http::connectTimeout(2)->timeout(3)->acceptJson()->get($url);
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

        $decoded = json_decode($body, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return $body;
        }

        $decoded['model'] = $model;

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
