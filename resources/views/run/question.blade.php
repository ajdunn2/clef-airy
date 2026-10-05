@php
    $question = array_merge([
        'name' => '',
        'type' => 'noul',
        'instructions' => '',
        'true' => '',
        'false' => '',
        'options' => [],
        'levels' => [],
    ], $question);

    $options = [];

    foreach ($question['options'] ?? [] as $option) {
        if (! is_array($option)) {
            continue;
        }

        $options[] = [
            'name' => (string) ($option['name'] ?? ''),
            'description' => (string) ($option['description'] ?? ''),
        ];
    }

    while (count($options) < 2) {
        $options[] = ['name' => '', 'description' => ''];
    }

    $levels = [];

    foreach ($question['levels'] ?? [] as $level) {
        if (is_scalar($level)) {
            $levels[] = (string) $level;
        }
    }

    while (count($levels) < 2) {
        $levels[] = '';
    }

    $field = 'w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25';
@endphp

<div class="grid gap-3 rounded-lg border border-line bg-canvas/60 p-3" data-question data-type="{{ $question['type'] }}">
    <div data-question-actions class="-mx-1.5 -mt-1.5 flex items-center justify-between gap-2">
        <span data-question-number class="grid size-5 shrink-0 place-items-center rounded-full border border-line bg-white text-[11px] font-medium leading-none tabular-nums text-ink">{{ (int) $index + 1 }}</span>
        <div class="flex items-center gap-1">
        <div data-move-questions>
            <button type="button" data-move-question="up" class="rounded p-0.5 text-muted hover:bg-white hover:text-ink disabled:opacity-40" aria-label="Move question up">
                <x-lucide-chevron-up class="size-3" aria-hidden="true" />
            </button>
            <button type="button" data-move-question="down" class="rounded p-0.5 text-muted hover:bg-white hover:text-ink disabled:opacity-40" aria-label="Move question down">
                <x-lucide-chevron-down class="size-3" aria-hidden="true" />
            </button>
        </div>
        <button type="button" data-remove-question class="rounded p-0.5 text-muted hover:bg-white hover:text-ink" aria-label="Remove question">
            <x-lucide-x class="size-3" aria-hidden="true" />
        </button>
        </div>
    </div>
    <div class="grid gap-3">
    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem]">
        <div class="grid gap-1.5">
            <label class="text-[13px] font-medium" for="question-name-{{ $index }}">Name</label>
            <input id="question-name-{{ $index }}" name="questions[{{ $index }}][name]" value="{{ $question['name'] }}" class="{{ $field }}">
        </div>
        <div class="grid gap-1.5">
            <label class="text-[13px] font-medium" for="question-type-{{ $index }}">Type</label>
            <select id="question-type-{{ $index }}" name="questions[{{ $index }}][type]" data-type-select class="{{ $field }}">
                @foreach (['noul' => 'Yes / No', 'choice' => 'Choice', 'score' => 'Score'] as $value => $label)
                    <option value="{{ $value }}" @selected($question['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-1.5">
        <label class="text-[13px] font-medium" for="question-instructions-{{ $index }}">Instructions</label>
        <input id="question-instructions-{{ $index }}" name="questions[{{ $index }}][instructions]" value="{{ $question['instructions'] }}" class="{{ $field }}">
    </div>

    <div data-criteria="noul" class="grid gap-3 sm:grid-cols-2">
        <div class="grid gap-1.5">
            <label class="text-[13px] font-medium" for="question-true-{{ $index }}">True&hellip; when</label>
            <input id="question-true-{{ $index }}" name="questions[{{ $index }}][true]" value="{{ $question['true'] }}" class="{{ $field }}">
        </div>
        <div class="grid gap-1.5">
            <label class="text-[13px] font-medium" for="question-false-{{ $index }}">False&hellip; when</label>
            <input id="question-false-{{ $index }}" name="questions[{{ $index }}][false]" value="{{ $question['false'] }}" class="{{ $field }}">
        </div>
    </div>

    <div data-criteria="choice" class="grid gap-2">
        <p class="text-[13px] font-medium">Options</p>
        <div class="grid gap-2" data-options>
            @foreach ($options as $optionIndex => $option)
                <div class="flex items-start gap-2" data-option>
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[9rem_minmax(0,1fr)]">
                        <input name="questions[{{ $index }}][options][{{ $optionIndex }}][name]" value="{{ $option['name'] }}" placeholder="Name" aria-label="Option name" class="{{ $field }}">
                        <input name="questions[{{ $index }}][options][{{ $optionIndex }}][description]" value="{{ $option['description'] }}" placeholder="Description" aria-label="Option description" class="{{ $field }}">
                    </div>
                    <button type="button" data-remove-option class="rounded-lg px-2.5 py-2 text-muted hover:bg-white hover:text-ink" aria-label="Remove option"><x-lucide-minus class="size-3" aria-hidden="true" /></button>
                </div>
            @endforeach
        </div>
        <div>
            <button type="button" data-add-option class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-[13px] font-medium" aria-label="Add option">+</button>
        </div>
    </div>

    <div data-criteria="score" class="grid gap-2">
        <div class="grid gap-0.5">
            <p class="text-[13px] font-medium">Levels</p>
            <p class="text-[13px] text-muted">Lowest first. Jev supports up to 10 levels; Ollama supports up to 26.</p>
        </div>
        <div class="grid gap-2" data-levels>
            @foreach ($levels as $levelIndex => $level)
                <div class="flex items-center gap-2" data-level>
                    <input name="questions[{{ $index }}][levels][{{ $levelIndex }}]" value="{{ $level }}" placeholder="Level" aria-label="Score level" class="{{ $field }}">
                    <button type="button" data-remove-level class="rounded-lg px-2.5 py-2 text-muted hover:bg-white hover:text-ink" aria-label="Remove level"><x-lucide-minus class="size-3" aria-hidden="true" /></button>
                </div>
            @endforeach
        </div>
        <div>
            <button type="button" data-add-level class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-[13px] font-medium" aria-label="Add level">+</button>
        </div>
    </div>
    </div>
</div>
