<div class="grid min-w-0 gap-3">
    <div class="flex items-center gap-2">
        <h2 class="text-[13px] font-medium">Response</h2>
        <span @class([
            'rounded-full px-2 py-0.5 text-xs font-medium',
            'bg-red-50 text-red-700' => $result['status'] === null || $result['status'] >= 400,
            'bg-ink/8 text-ink' => is_int($result['status']) && $result['status'] < 400,
        ])>{{ $result['status'] === null ? 'Failed' : $result['status'] }}</span>
    </div>
    <textarea data-response-body hidden>{{ $result['body'] }}</textarea>
    <p class="break-all font-mono text-xs text-muted">{{ $result['url'] }}</p>

    <div data-switch class="grid gap-3">
        <div class="flex justify-end">
            <div class="flex rounded-lg bg-canvas p-0.5 text-[13px]">
                <label class="cursor-pointer rounded-md px-2.5 py-1 text-muted">
                    <input type="radio" name="result_view" value="form" data-pick="form" class="sr-only" checked>
                    Easy
                </label>
                <label class="cursor-pointer rounded-md px-2.5 py-1 text-muted">
                    <input type="radio" name="result_view" value="json" data-pick="json" class="sr-only">
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
                    @if ($result['decision']['usage'])
                        <p class="text-[13px] text-muted">{{ $result['decision']['usage'] }}</p>
                    @endif
                @endif
            @else
                <pre data-highlight-json class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-canvas p-3 font-mono text-[13px]">{{ $result['pretty'] ?? $result['body'] }}</pre>
            @endif
        </div>

        <div data-panel="json">
            <pre data-highlight-json class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-canvas p-3 font-mono text-[13px]">{{ $result['pretty'] ?? $result['body'] }}</pre>
        </div>
    </div>
</div>
