<?php

namespace App\Http\Controllers;

use App\Models\SystemOneSetting;
use App\Services\SystemOneClient;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function edit(SystemOneClient $client): View
    {
        $setting = SystemOneSetting::current();
        $apiUrl = $setting?->base_url ?: 'http://localhost:11434';
        $model = $client->modelForApi($apiUrl, $setting?->model);
        $models = $client->models($apiUrl, $model);

        return view('configuration.edit', [
            'apiUrl' => $setting?->base_url,
            'username' => $setting?->username,
            'yesThreshold' => $setting?->yes_threshold ?? 50,
            'authType' => $setting?->auth_type ?? 'none',
            'hasPassword' => filled($setting?->getRawOriginal('password')),
            'passwordNeedsReset' => $setting !== null
                && ! str_starts_with((string) $setting->getRawOriginal('password'), 'native:v1:')
                && $setting->passwordNeedsReset(),
            'model' => $model,
            'models' => $models['models'],
            'modelError' => $models['error'],
        ]);
    }

    public function update(Request $request, SystemOneClient $client): RedirectResponse
    {
        $setting = SystemOneSetting::current();

        $validated = $request->validate([
            'api_url' => [
                'required',
                'string',
                'max:2048',
                'url:http,https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $parts = parse_url($value);

                    if (isset($parts['user']) || isset($parts['pass'])) {
                        $fail('Put the username and password in their own fields.');
                    }
                },
            ],
            'username' => ['nullable', 'string', 'max:255'],
            'auth_type' => ['sometimes', Rule::in(['basic', 'bearer', 'none'])],
            'password' => ['nullable', 'string', 'max:2000'],
            'remove_password' => ['sometimes', 'boolean'],
            'model' => ['nullable', 'string', 'max:255'],
            'yes_threshold' => ['sometimes', 'required', 'integer', 'between:0,100'],
        ], [
            'api_url.required' => 'Enter the API URL.',
            'api_url.url' => 'Enter an http or https API URL.',
        ]);

        $credentialsChanged = $setting !== null && (
            $setting->base_url !== $validated['api_url']
            || $setting->auth_type !== ($validated['auth_type'] ?? $setting->auth_type)
        );

        $setting ??= new SystemOneSetting;
        $setting->yes_threshold = $validated['yes_threshold'] ?? $setting->yes_threshold ?? 50;
        $setting->base_url = $validated['api_url'];
        $setting->username = $validated['username'] ?? '';
        $setting->auth_type = $validated['auth_type'] ?? $setting->auth_type ?? 'basic';

        if (filled($validated['password'] ?? null)) {
            $setting->password = $validated['password'];
        } elseif ($credentialsChanged || $request->boolean('remove_password') || ! filled($setting->getRawOriginal('password'))) {
            $setting->password = '';
        }

        $setting->model = $client->modelForApi($setting->base_url, $validated['model'] ?? null);

        $setting->save();

        return redirect()
            ->route('configuration.edit')
            ->with('status', 'Saved.');
    }
}
