<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Clef Airy Decisions API Tester')</title>
        <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">
        <script>
            try {
                if (localStorage.getItem('clef-examples') === '0') {
                    document.documentElement.dataset.examples = 'hidden';
                }
            } catch {}
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-shell text-ink antialiased">
        @php($workspace = request()->routeIs('run.*', 'calls.show'))
        <div @if ($workspace) x-data="runWorkspace" x-on:keydown.window="shortcut($event)" @endif class="flex min-h-screen flex-col md:h-screen md:flex-row md:overflow-hidden">
            <aside class="flex shrink-0 flex-col border-b border-line bg-shell md:h-screen md:w-56 md:border-r md:border-b-0">
                <div class="flex items-center gap-2 px-4 py-3 md:pt-4">
                    <img src="{{ asset('icon.png') }}" alt="" class="size-7" width="28" height="28">
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold tracking-tight">Clef Airy</span>
                        <span class="text-[11px] text-muted">Decisions API Tester</span>
                    </div>
                </div>

                <nav
                    x-data="{
                        examples: document.documentElement.dataset.examples !== 'hidden',
                        pendingName: '',
                        pendingForm: '',
                        toggleExamples() {
                            this.examples = ! this.examples;
                            document.documentElement.dataset.examples = this.examples ? '' : 'hidden';
                            try { localStorage.setItem('clef-examples', this.examples ? '1' : '0'); } catch {}
                        },
                        askRemove(name, formId) {
                            this.pendingName = name;
                            this.pendingForm = formId;
                            this.$refs.removeDialog.showModal();
                        },
                    }"
                    class="flex flex-wrap gap-1 px-2 pb-3 md:min-h-0 md:flex-1 md:flex-col md:flex-nowrap md:px-3 md:pb-3"
                    aria-label="Sections"
                >
                    <a
                        href="{{ route('run.create') }}"
                        @if (request()->routeIs('run.create')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.create'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.create'),
                        ])
                    >
                        <x-lucide-clef-treble class="size-4 shrink-0" aria-hidden="true" />
                        Run
                    </a>
                    <a
                        data-example
                        href="{{ route('run.example') }}"
                        @if (request()->routeIs('run.example')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example'),
                        ])
                    >
                        <x-lucide-scroll-text class="size-4 shrink-0" aria-hidden="true" />
                        Example #1
                    </a>
                    <a
                        data-example
                        href="{{ route('run.example2') }}"
                        @if (request()->routeIs('run.example2')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example2'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example2'),
                        ])
                    >
                        <x-lucide-scroll-text class="size-4 shrink-0" aria-hidden="true" />
                        Example #2
                    </a>
                    <a
                        data-example
                        href="{{ route('run.example3') }}"
                        @if (request()->routeIs('run.example3')) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium',
                            'bg-ink text-white' => request()->routeIs('run.example3'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('run.example3'),
                        ])
                    >
                        <x-lucide-scroll-text class="size-4 shrink-0" aria-hidden="true" />
                        Example #3
                    </a>
                    <button type="button" x-on:click="toggleExamples()" x-text="examples ? 'Hide examples' : 'Show examples'" :aria-expanded="examples" class="w-full py-0.5 text-center text-[11px] text-muted hover:text-ink">Hide examples</button>
                    <div class="contents md:flex md:min-h-0 md:flex-1 md:flex-col md:gap-1 md:overflow-y-auto">
                        @foreach ($savedCalls as $menuCall)
                            @php($current = request()->routeIs('calls.show') && request()->route('savedCall')?->id === $menuCall->id)
                            <div @class([
                                'flex w-full min-w-0 items-center gap-1 rounded-lg pr-1',
                                'bg-ink text-white' => $current,
                                'hover:bg-ink/6' => ! $current,
                            ])>
                                <a
                                    href="{{ route('calls.show', $menuCall) }}"
                                    @if ($current) aria-current="page" @endif
                                    title="{{ $menuCall->name }}"
                                    @class([
                                        'flex min-w-0 flex-1 items-center gap-2.5 py-1.5 pl-2.5 text-[13px] font-medium',
                                        'text-white' => $current,
                                        'text-ink/75' => ! $current,
                                    ])
                                >
                                    <x-lucide-cassette-tape class="size-4 shrink-0" aria-hidden="true" />
                                    <span class="min-w-0 flex-1 truncate">{{ $menuCall->name }}</span>
                                </a>
                                <button
                                    type="button"
                                    x-on:click="askRemove({{ \Illuminate\Support\Js::from($menuCall->name) }}, 'remove-bookmark-{{ $menuCall->id }}')"
                                    aria-label="Remove {{ $menuCall->name }}"
                                    @class([
                                        'grid size-5 shrink-0 place-items-center rounded',
                                        'text-white/80 hover:bg-white/15 hover:text-white' => $current,
                                        'text-muted hover:bg-ink/8 hover:text-ink' => ! $current,
                                    ])
                                ><x-lucide-x class="size-3" aria-hidden="true" /></button>
                            </div>
                            <form id="remove-bookmark-{{ $menuCall->id }}" method="POST" action="{{ route('calls.destroy', $menuCall) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                    </div>
                    <a
                        href="{{ route('configuration.edit') }}"
                        @class([
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium md:mt-auto',
                            'bg-ink text-white' => request()->routeIs('configuration.*'),
                            'text-ink/75 hover:bg-ink/6' => ! request()->routeIs('configuration.*'),
                        ])
                    >
                        <x-lucide-settings class="size-4 shrink-0" aria-hidden="true" />
                        Configuration
                    </a>
                    <dialog
                        x-ref="removeDialog"
                        x-on:click="if ($event.target === $el) $el.close()"
                        x-on:close="pendingForm = ''"
                        aria-labelledby="remove-bookmark-title"
                        class="m-auto w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-white p-4 text-ink shadow-lg shadow-black/10 backdrop:bg-ink/30"
                    >
                        <div class="grid gap-3">
                            <h2 id="remove-bookmark-title" class="text-sm font-semibold">Remove bookmark</h2>
                            <p class="text-[13px]">Remove <span class="font-medium" style="overflow-wrap:anywhere" x-text="pendingName"></span>?</p>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" x-on:click="$refs.removeDialog.close()" class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Cancel</button>
                                <button type="submit" :form="pendingForm" :disabled="pendingForm === ''" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Remove</button>
                            </div>
                        </div>
                    </dialog>
                </nav>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col bg-canvas">
                <header class="flex h-12 shrink-0 items-center gap-3 border-b border-line px-4 md:px-5">
                    <h1 class="text-sm font-semibold">@yield('heading')</h1>
                    <p class="truncate text-[13px] text-muted">@yield('summary')</p>
                    <div class="ml-auto shrink-0">@yield('actions')</div>
                </header>
                <div class="min-h-0 flex-1 overflow-auto px-4 py-5 md:px-6">
                    <div @class(['grid w-full min-w-0 gap-4', 'max-w-2xl' => ! $workspace])>
                        @yield('content')
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
