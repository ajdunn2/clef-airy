<div class="grid min-w-0 gap-3">
    <div class="flex items-center gap-2">
        <span @class([
            'rounded-full px-2 py-0.5 font-mono text-xs font-medium',
            'bg-red-50 text-red-700' => $result['status'] === null || $result['status'] >= 400,
            'bg-ink/8 text-ink' => is_int($result['status']) && $result['status'] < 400,
        ])>{{ $result['status'] === null ? 'Failed' : 'HTTP '.$result['status'] }}</span>
    </div>
    <textarea data-response-body hidden>{{ $result['body'] }}</textarea>
    <p class="break-all font-mono text-xs text-muted">{{ $result['url'] }}</p>

    @php
        $usage = $result['decision']['usage'] ?? null;
        $duration = $result['duration'] ?? null;
        $questionsCount = isset($result['questions_count']) && $result['questions_count'] !== null
            ? $result['questions_count'].' '.\Illuminate\Support\Str::plural('question', $result['questions_count']).' sent'
            : null;
    @endphp

    <div data-switch class="grid gap-3">
        <div class="flex justify-end">
            <div class="flex rounded-lg bg-canvas p-0.5 text-[13px]">
                <label class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-muted">
                    <input type="radio" name="result_view" value="form" data-pick="form" class="sr-only" checked>
                    <x-lucide-baby class="size-3.5 shrink-0" aria-hidden="true" />
                    Easy
                </label>
                <label class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-muted">
                    <input type="radio" name="result_view" value="json" data-pick="json" class="sr-only">
                    <x-lucide-braces class="size-3.5 shrink-0" aria-hidden="true" />
                    JSON
                </label>
            </div>
        </div>

        <div data-panel="form" class="grid gap-3">
            @if (is_array($result['decision'] ?? null))
                @if ($result['decision']['error'])
                    <article class="grid gap-2 rounded-lg bg-red-50 p-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="text-[13px] font-medium text-red-700">API error</h3>
                        </div>
                        <p class="text-[13px] text-red-700">{{ $result['decision']['error'] }}</p>
                    </article>
                @else
                    @foreach ($result['decision']['answers'] as $answer)
                        <article class="grid gap-2 rounded-lg bg-canvas p-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <h3 class="text-[13px] font-medium">{{ $answer['name'] }}</h3>
                                <p class="text-sm font-semibold">{{ $answer['headline'] }}</p>
                            </div>
                            @if ($answer['detail'])
                                <p class="text-[13px] text-muted">{{ $answer['detail'] }}</p>
                            @endif
                            @if ($answer['rows'] !== [])
                                <ul class="grid gap-2">
                                    @foreach ($answer['rows'] as $row)
                                        <li class="grid grid-cols-[minmax(0,7rem)_minmax(0,1fr)_2.5rem] items-center gap-2 text-[13px]">
                                            <span @class(['truncate font-medium' => $row['selected'], 'truncate text-muted' => ! $row['selected']])>{{ $row['label'] }}</span>
                                            <span class="h-1.5 overflow-hidden rounded-full bg-line" aria-hidden="true">
                                                <span @class([
                                                    'block h-full rounded-full',
                                                    'bg-accent' => $row['selected'],
                                                    'bg-ink/25' => ! $row['selected'],
                                                ]) style="width: {{ (int) ($row['share'] ?? 0) }}%"></span>
                                            </span>
                                            <span @class(['text-right', 'font-medium' => $row['selected'], 'text-muted' => ! $row['selected']])>{{ $row['value'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($answer['confidence'])
                                <p class="text-[13px] text-muted">Confidence {{ $answer['confidence'] }}</p>
                            @endif
                        </article>
                    @endforeach
                @endif
            @else
                <pre data-highlight-json class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-canvas p-3 font-mono text-[13px]">{{ $result['pretty'] ?? $result['body'] }}</pre>
            @endif
        </div>

        <div data-panel="json" class="grid gap-3">
            <pre data-highlight-json class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-canvas p-3 font-mono text-[13px]">{{ $result['pretty'] ?? $result['body'] }}</pre>
        </div>
    </div>
    @if ($usage || $duration || $questionsCount)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-muted">
            @if ($usage)
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide-playing-cards-fan class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>{{ $usage }}</span>
                </span>
            @endif
            @if ($duration)
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide-timer class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>{{ $duration }}</span>
                </span>
            @endif
            @if ($questionsCount)
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide-message-circle-question-mark class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>{{ $questionsCount }}</span>
                </span>
            @endif
        </div>
    @endif
</div>
