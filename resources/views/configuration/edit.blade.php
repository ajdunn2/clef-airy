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

    <form method="POST" action="{{ route('configuration.update') }}" x-data="{ authType: {{ \Illuminate\Support\Js::from(old('auth_type', $authType)) }} }" class="grid gap-4 rounded-xl border border-line bg-white p-5" autocomplete="off">
        @csrf
        @method('PUT')

        <div class="grid gap-1.5">
            <label for="api_url" class="text-[13px] font-medium">API URL</label>
            <input
                id="api_url"
                name="api_url"
                type="url"
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
            <label for="model" class="text-[13px] font-medium">Model</label>
            <select id="model" name="model" class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @if ($modelError)
                    <option value="" selected disabled>Models unavailable</option>
                @endif
                @foreach ($models as $name)
                    <option value="{{ $name }}" @selected(old('model', $model) === $name)>{{ $name }}</option>
                @endforeach
            </select>
            @if ($modelError)
                <p class="whitespace-pre-line text-[13px] text-red-700" role="alert">{{ $modelError }}</p>
            @endif
            @error('model')
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
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                @if ($hasPassword) placeholder="********" @endif
                class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
            >
            @if ($passwordNeedsReset)
                <p class="text-[13px] text-muted">Enter your API password to replace the unreadable saved value.</p>
            @elseif ($hasPassword)
                <p class="text-[13px] text-muted">A password is saved. Leave this blank to keep it.</p>
            @endif
            <p x-show="authType === 'bearer'" class="text-[13px] text-muted">Note: For Jev, enter your TypeSafe key here. Use <small><code>https://api.typesafe.ai</code></small> with model jev-latest.</p>
            @if ($hasPassword)
                <label class="flex items-center gap-2 text-[13px]">
                    <input type="checkbox" name="remove_password" value="1" @checked(old('remove_password'))>
                    Remove saved password (a new password takes precedence)
                </label>
            @endif
            @error('password')
                <p class="text-[13px] text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Save</button>
        </div>
    </form>
@endsection
