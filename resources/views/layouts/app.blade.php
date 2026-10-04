<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Clef Airy Decisions')</title>
        <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-shell text-ink antialiased">
        <div @if (request()->routeIs('run.*')) x-data="runWorkspace" x-on:keydown.window="shortcut($event)" @endif class="flex min-h-screen flex-col md:h-screen md:flex-row md:overflow-hidden">
            <aside class="flex shrink-0 flex-col border-b border-line bg-shell md:h-screen md:w-56 md:border-r md:border-b-0">
                <div class="flex items-center gap-2 px-4 py-3 md:pt-4">
                    <img src="{{ asset('icon.png') }}" alt="" class="size-7" width="28" height="28">
                    <span class="text-sm font-semibold tracking-tight">Clef Airy Decisions</span>
                </div>

                <nav class="flex gap-1 px-2 pb-3 md:flex-1 md:flex-col md:px-3 md:pb-3" aria-label="Sections">
                    <a
                        href="{{ route('run.create') }}"
                        @if (request()->routeIs('run.create')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.create'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.create'),
                        ])
                    >
                        <svg viewBox="0 0 16 16" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3.5v9l8-4.5-8-4.5Z" />
                        </svg>
                        Run
                    </a>
                    <a
                        href="{{ route('run.example') }}"
                        @if (request()->routeIs('run.example')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example'),
                        ])
                    >
                        <svg viewBox="0 0 16 16" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3.5v9l8-4.5-8-4.5Z" />
                        </svg>
                        Example #1
                    </a>
                    <a
                        href="{{ route('run.example2') }}"
                        @if (request()->routeIs('run.example2')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example2'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example2'),
                        ])
                    >
                        <svg viewBox="0 0 16 16" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3.5v9l8-4.5-8-4.5Z" />
                        </svg>
                        Example #2
                    </a>
                    <a
                        href="{{ route('run.example3') }}"
                        @if (request()->routeIs('run.example3')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example3'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example3'),
                        ])
                    >
                        <svg viewBox="0 0 16 16" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3.5v9l8-4.5-8-4.5Z" />
                        </svg>
                        Example #3
                    </a>
                    <a
                        href="{{ route('configuration.edit') }}"
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium md:mt-auto',
                            'bg-ink text-white' => request()->routeIs('configuration.*'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('configuration.*'),
                        ])
                    >
                        <svg viewBox="0 0 16 16" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.4 2.2h3.2l.4 1.5a4.8 4.8 0 0 1 1.2.7l1.5-.5 1.6 2.7-1.1 1.1a4.8 4.8 0 0 1 0 1.4l1.1 1.1-1.6 2.7-1.5-.5a4.8 4.8 0 0 1-1.2.7l-.4 1.5H6.4l-.4-1.5a4.8 4.8 0 0 1-1.2-.7l-1.5.5-1.6-2.7 1.1-1.1a4.8 4.8 0 0 1 0-1.4L1.7 6.6l1.6-2.7 1.5.5a4.8 4.8 0 0 1 1.2-.7l.4-1.5Z" />
                            <circle cx="8" cy="8" r="1.6" />
                        </svg>
                        Configuration
                    </a>
                </nav>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col bg-canvas">
                <header class="flex h-12 shrink-0 items-center gap-3 border-b border-line px-4 md:px-5">
                    <h1 class="text-sm font-semibold">@yield('heading')</h1>
                    <p class="truncate text-[13px] text-muted">@yield('summary')</p>
                    <div class="ml-auto shrink-0">@yield('actions')</div>
                </header>
                <div class="min-h-0 flex-1 overflow-auto px-4 py-5 md:px-6">
                    <div @class(['grid w-full min-w-0 gap-4', 'max-w-2xl' => ! request()->routeIs('run.*')])>
                        @yield('content')
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
