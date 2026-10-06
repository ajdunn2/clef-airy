@extends('layouts.app')

@section('title', 'Configuration — Clef Airy Decisions API Tester')

@section('heading', 'Configuration')

@section('content')
    @if (session('status'))
        <p class="text-[13px] text-muted" role="status">{{ session('status') }}</p>
    @endif

    @if ($passwordNeedsReset)
        <p class="text-[13px] text-red-700" role="alert">Your saved password cannot be decrypted. Re-enter it, or remove it if your API does not require a password.</p>
    @endif

    <form
        method="POST"
        action="{{ route('configuration.update') }}"
        x-data="{
            authType: {{ \Illuminate\Support\Js::from(old('auth_type', $authType)) }},
            apiUrl: {{ \Illuminate\Support\Js::from(old('api_url', $apiUrl ?: 'http://localhost:11434')) }},
            model: {{ \Illuminate\Support\Js::from(old('model', $model) ?? '') }},
            modelsUrl: {{ \Illuminate\Support\Js::from($apiUrl ?: 'http://localhost:11434') }},
            credentialsChanged: false,
            init() {
                this.$watch('apiUrl', () => {
                    this.model = '';
                    this.credentialsChanged = true;
                    this.$refs.password.value = '';
                    this.$refs.username.value = '';
                    if (this.$refs.removePassword) { this.$refs.removePassword.checked = true; }
                });
            },
            applyPreset(preset) {
                if (preset === 'ollama') {
                    this.apiUrl = 'http://localhost:11434';
                    this.authType = 'none';
                } else if (preset === 'typesafe') {
                    this.apiUrl = 'https://api.typesafe.ai';
                    this.authType = 'bearer';
                }
            }
        }"
        class="grid gap-4 rounded-xl border border-line bg-white p-5"
        autocomplete="off"
    >
        @csrf
        @method('PUT')

        <section class="grid gap-2 rounded-lg border border-line p-4" aria-labelledby="credentials-heading">
            <h2 id="credentials-heading" class="text-sm font-semibold">Credentials</h2>

            <div class="grid gap-1.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="api_url" class="text-[13px] font-medium">API URL</label>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-muted">Presets:</span>
                        <button
                            type="button"
                            x-on:click="applyPreset('ollama')"
                            class="inline-flex items-center gap-1 rounded-md border border-line bg-canvas px-2 py-0.5 font-medium text-ink/80 transition hover:border-ink/20 hover:bg-white hover:text-ink focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-accent"
                        >
                            Local Ollama
                        </button>
                        <button
                            type="button"
                            x-on:click="applyPreset('typesafe')"
                            class="inline-flex items-center gap-1 rounded-md border border-line bg-canvas px-2 py-0.5 font-medium text-ink/80 transition hover:border-ink/20 hover:bg-white hover:text-ink focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-accent"
                        >
                            TypeSafe AI
                        </button>
                    </div>
                </div>
                <input
                    id="api_url"
                    name="api_url"
                    type="url"
                    x-model="apiUrl"
                    x-on:input="$el.value = apiUrl = $el.value.trim()"
                    value="{{ old('api_url', $apiUrl ?: 'http://localhost:11434') }}"
                    required
                    class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                    placeholder="http://localhost:11434"
                >
                @error('api_url')
                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-1.5">
                <label for="auth_type" class="text-[13px] font-medium">Authentication</label>
                <select id="auth_type" name="auth_type" x-model="authType" class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                    @foreach (['none' => 'None (e.g. local Ollama)', 'basic' => 'Basic (e.g. protected API proxy)', 'bearer' => 'Bearer API key (e.g. Jev / TypeSafe)'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('auth_type', $authType) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('auth_type')
                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div x-cloak x-show="authType === 'basic'" class="grid gap-1.5">
                <label for="username" class="text-[13px] font-medium">Username (optional)</label>
                <input
                    x-ref="username"
                    id="username"
                    name="username"
                    type="text"
                    value="{{ old('username', $username) }}"
                    autocomplete="off"
                    class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                >
                @error('username')
                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div x-cloak x-show="authType !== 'none'" class="grid gap-1.5">
                <label for="password" x-text="authType === 'bearer' ? 'API key (optional)' : 'Password (optional)'" class="text-[13px] font-medium">Password / API key (optional)</label>
                <input
                    x-ref="password"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    @if ($hasPassword) placeholder="********" x-bind:placeholder="credentialsChanged ? '' : '********'" @endif
                    class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                >
                @if ($passwordNeedsReset)
                    <p class="text-[13px] text-muted">Enter your API password to replace the unreadable saved value.</p>
                @elseif ($hasPassword)
                    <p x-show="! credentialsChanged" class="text-[13px] text-muted">A password is saved. Leave this blank to keep it.<br>If requests report an unreadable password, enter it again here.</p>
                @endif
                @if ($hasPassword)
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="checkbox" x-ref="removePassword" name="remove_password" value="1" @checked(old('remove_password'))>
                        Remove saved password (a new password takes precedence)
                    </label>
                @endif
                @error('password')
                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                @enderror
            </div>

        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Save</button>
        </div>

        <section class="grid gap-2 rounded-lg border border-line p-4" aria-labelledby="model-heading">
        <div class="grid gap-1.5">
            <label id="model-heading" for="model" class="text-sm font-semibold">Default Model</label>
            <select id="model" name="model" x-model="model" x-bind:disabled="apiUrl !== modelsUrl" class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @if ($modelError)
                    <option value="" selected disabled>Models unavailable</option>
                @else
                    <option value="" @selected(! filled(old('model', $model)))>Choose a model…</option>
                @endif
                @foreach ($models as $name)
                    <option x-bind:hidden="apiUrl !== modelsUrl" x-bind:disabled="apiUrl !== modelsUrl" value="{{ $name }}" @selected(old('model', $model) === $name)>{{ $name }}</option>
                @endforeach
            </select>
            <p x-cloak x-show="apiUrl !== modelsUrl" class="text-[13px] text-muted">Save to load models from the new API URL.</p>
            @if ($modelError)
                <p x-show="apiUrl === modelsUrl" class="whitespace-pre-line text-[13px] text-red-700" role="alert">{{ $modelError }}</p>
            @endif
            @error('model')
                <p class="text-[13px] text-red-700">{{ $message }}</p>
            @enderror
        </div>
        </section>

        <section class="grid gap-2 rounded-lg border border-line  p-4" aria-labelledby="threshold-heading">
            <h2 id="threshold-heading" class="text-sm font-semibold">Yes/No decision threshold</h2>
            <input id="yes_threshold" name="yes_threshold" type="number" min="0" max="100" step="1" required value="{{ old('yes_threshold', $yesThreshold) }}" aria-describedby="threshold-help" class="w-24 rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            <p id="threshold-help" class="text-[13px] text-muted">Applies to all yes/no results. <strong>Default: 50%</strong>.<br>This changes the displayed decision, not the API request or confidence.</p>
            @error('yes_threshold')
                <p class="text-[13px] text-red-700">{{ $message }}</p>
            @enderror
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Save</button>
        </div>

    </form>
@endsection
