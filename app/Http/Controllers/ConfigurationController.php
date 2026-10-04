<?php

namespace App\Http\Controllers;

use App\Models\SystemOneSetting;
use App\Services\SystemOneClient;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function edit(SystemOneClient $client): View
    {
        $setting = SystemOneSetting::current();
        $apiUrl = $setting?->base_url ?: 'http://localhost:11434';
        $models = $client->models($apiUrl, $setting?->model ?: 'clef-flash');

        return view('configuration.edit', [
            'apiUrl' => $setting?->base_url,
            'username' => $setting?->username,
            'hasPassword' => filled($setting?->getRawOriginal('password')),
            'passwordNeedsReset' => $setting !== null
                && ! str_starts_with((string) $setting->getRawOriginal('password'), 'native:v1:')
                && $setting->passwordNeedsReset(),
            'model' => $setting?->model ?: 'clef-flash',
            'models' => $models['models'],
            'modelsFromApi' => $models['fromApi'],
        ]);
    }

    public function update(Request $request): RedirectResponse
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
            'password' => ['nullable', 'string', 'max:2000'],
            'remove_password' => ['sometimes', 'boolean'],
            'model' => ['nullable', 'string', 'max:255'],
        ], [
            'api_url.required' => 'Enter the API URL.',
            'api_url.url' => 'Enter an http or https API URL.',
        ]);

        $setting ??= new SystemOneSetting;
        $setting->base_url = $validated['api_url'];
        $setting->username = $validated['username'] ?? '';

        if (filled($validated['password'] ?? null)) {
            $setting->password = $validated['password'];
        } elseif ($request->boolean('remove_password') || ! filled($setting->getRawOriginal('password'))) {
            $setting->password = '';
        }

        $setting->model = filled($validated['model'] ?? null) ? $validated['model'] : ($setting->model ?: 'clef-flash');

        $setting->save();

        return redirect()
            ->route('configuration.edit')
            ->with('status', 'Saved.');
    }
}
