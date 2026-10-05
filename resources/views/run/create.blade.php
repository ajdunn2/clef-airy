@extends('layouts.app')

@section('title', $pageTitle.' — Clef Airy Decisions')

@section('heading', $pageTitle)

@section('summary', 'Send one request and read the response.')

@section('actions')
    @if ($configured)
        <button type="submit" form="run-form" data-send :disabled="sending" x-text="sending ? 'Sending…' : 'Send'" class="min-w-24 rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90 disabled:cursor-progress disabled:opacity-70">Send</button>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <p class="text-[13px] text-muted" role="status">{{ session('status') }}</p>
    @endif

    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    @if ($configured)
        <form id="run-form" method="POST" action="{{ route($storeRoute) }}" data-run x-ref="form" x-on:submit.prevent="send" :aria-busy="sending" :data-sending="sending" class="grid min-w-0 gap-4">
            @csrf
            <fieldset :disabled="sending" class="grid min-w-0 gap-4">

            <div class="grid gap-4 rounded-xl border border-line bg-white p-4 shadow-sm shadow-black/3">
                <div class="grid gap-1.5">
                    <label for="model" class="text-[13px] font-medium">Model</label>
                    <select id="model" name="model" class="rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        @foreach ($models as $name)
                            <option value="{{ $name }}" @selected(old('model', $model) === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[13px] text-muted">{{ $modelsFromApi ? 'Installed on this Ollama server.' : 'Library models. Installed models appear here when the API URL can be reached.' }}</p>
                    @error('model')
                        <p class="text-[13px] text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <div class="grid gap-1.5">
                        <label for="method" class="text-[13px] font-medium">Method</label>
                        <select id="method" name="method" class="rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                                <option value="{{ $method }}" @selected(old('method', 'POST') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                        @error('method')
                            <p class="text-[13px] text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-1.5">
                        <label for="path" class="text-[13px] font-medium">Path</label>
                        <input
                            id="path"
                            name="path"
                            type="text"
                            value="{{ old('path', '/v1/systemone') }}"
                            required
                            class="rounded-lg border border-line bg-white px-3 py-2 font-mono text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                            placeholder="/v1/systemone"
                        >
                        @error('path')
                            <p class="text-[13px] text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>

            <div class="grid gap-4 rounded-xl border border-line bg-white p-4 shadow-sm shadow-black/3">
                <div data-switch class="grid gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-[13px] font-medium">Request</span>
                        <div class="flex rounded-lg bg-canvas p-0.5 text-[13px]">
                            <label class="cursor-pointer rounded-md px-2.5 py-1 text-muted">
                                <input type="radio" name="body_mode" value="form" data-pick="form" class="sr-only" @checked(old('body_mode', 'form') === 'form')>
                                Form
                            </label>
                            <label class="cursor-pointer rounded-md px-2.5 py-1 text-muted">
                                <input type="radio" name="body_mode" value="json" data-pick="json" class="sr-only" @checked(old('body_mode', 'form') === 'json')>
                                JSON
                            </label>
                        </div>
                    </div>

                    <div data-panel="form" class="grid gap-3">
                        <div class="grid gap-1.5">
                            <label for="state" class="text-[13px] font-medium">State</label>
                            <textarea id="state" name="state" rows="3" autofocus class="rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">{{ old('state', $isExample ? $exampleState : '') }}</textarea>
                            @error('state')
                                <p class="text-[13px] text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-3" data-questions>
                            @foreach (old('questions', $isExample ? $exampleQuestions : [[]]) as $index => $question)
                                @include('run.question', ['index' => $index, 'question' => $question])
                            @endforeach
                        </div>

                        @error('questions')
                            <p class="text-[13px] text-red-700">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-between gap-3">
                            <button type="button" data-add-question class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Add question</button>
                        </div>
                    </div>

                    <div data-panel="json" class="grid gap-1.5">
                        <label for="body" class="text-[13px] font-medium">JSON</label>
                        <div data-json-editor class="relative grid overflow-hidden rounded-lg border border-line bg-white focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/25">
                            <pre data-json-preview aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden whitespace-pre p-0 px-3 py-2 font-mono text-[13px] leading-5"></pre>
                            <textarea id="body" name="body" rows="16" wrap="off" spellcheck="false" class="relative w-full resize-y bg-transparent px-3 py-2 font-mono text-[13px] leading-5 outline-none">{{ old('body', $isExample ? $defaultBody : '') }}</textarea>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" data-format-json class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Format JSON</button>
                            <span data-json-status role="status" class="text-[13px] text-muted"></span>
                        </div>
                        <p class="text-[13px] text-muted">Switching views fills the other from this one and replaces its edits. The selected model is added when you send.</p>
                        @error('body')
                            <p class="text-[13px] text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <template id="question-template">
                    @include('run.question', ['index' => '__INDEX__', 'question' => []])
                </template>


                <template id="option-template">
                    <div class="flex items-start gap-2" data-option>
                        <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[9rem_minmax(0,1fr)]">
                            <input name="questions[__INDEX__][options][__OPTION__][name]" value="" placeholder="Name" aria-label="Option name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                            <input name="questions[__INDEX__][options][__OPTION__][description]" value="" placeholder="Description" aria-label="Option description" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        </div>
                        <button type="button" data-remove-option class="rounded-lg px-2.5 py-2 text-[13px] text-muted hover:bg-white hover:text-ink" aria-label="Remove option">−</button>
                    </div>
                </template>

                <template id="level-template">
                    <div class="flex items-center gap-2" data-level>
                        <input name="questions[__INDEX__][levels][__LEVEL__]" value="" placeholder="Level" aria-label="Score level" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        <button type="button" data-remove-level class="rounded-lg px-2.5 py-2 text-[13px] text-muted hover:bg-white hover:text-ink" aria-label="Remove level">−</button>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-3">
                    <span class="text-[13px] text-muted">Ctrl/⌘⏎ to send</span>
                    <button type="submit" data-send x-text="sending ? 'Sending…' : 'Send'" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Send</button>
                </div>
            </div>
            </fieldset>
        </form>
    @else
        <div class="rounded-xl border border-line bg-white p-4 shadow-sm shadow-black/3">
            <p class="text-[13px] text-ink/80">Save the API URL before sending a request.</p>
            <a href="{{ route('configuration.edit') }}" class="mt-3 inline-block text-[13px] font-medium text-accent">Go to Configuration</a>
        </div>
    @endif

    <section class="min-w-0 self-start rounded-xl border border-line bg-white p-4 shadow-sm shadow-black/3 lg:sticky lg:top-0" aria-label="Response" :aria-busy="sending">
        <div class="mb-3 flex min-h-8 items-center justify-between gap-3">
            <p role="status" class="text-[13px] text-muted" x-text="sending ? 'Sending request…' : feedback"></p>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="copyRequest" :disabled="sending" x-text="copied === 'request' ? 'Copied' : 'Copy request'" :class="copied === 'request' ? 'border-accent text-accent' : 'border-line'" class="min-w-27 rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors duration-300 disabled:opacity-40">Copy request</button>
                <button type="button" x-on:click="copyResponse" :disabled="!hasResponse" x-text="copied === 'response' ? 'Copied' : 'Copy response'" :class="copied === 'response' ? 'border-accent text-accent' : 'border-line'" class="min-w-30 rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors duration-300 disabled:opacity-40">Copy response</button>
            </div>
        </div>
        <p x-cloak x-show="error" x-text="error" role="alert" class="mb-3 whitespace-pre-line text-[13px] text-red-700"></p>
        <div x-ref="response" class="min-w-0">
            @if (is_array($result))
                @include('run.response', ['result' => $result])
            @else
                <div class="grid min-h-64 place-items-center rounded-lg bg-canvas px-4 text-center text-[13px] text-muted">Your response will appear here.</div>
            @endif
        </div>
    </section>
    </div>

    <script>
        const runForm = document.querySelector('[data-run]');

        document.querySelector('[data-add-question]')?.addEventListener('click', () => {
            if (runForm?.dataset.sending === 'true') {
                return;
            }

            const list = document.querySelector('[data-questions]');
            const template = document.querySelector('#question-template');
            const indexes = [...list.querySelectorAll('[name^="questions["]')].map((input) => Number(input.name.match(/^questions\[(\d+)\]/)?.[1] ?? -1));
            const index = Math.max(-1, ...indexes) + 1;

            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        });

        document.addEventListener('click', (event) => {
            if (runForm?.dataset.sending === 'true') {
                return;
            }

            const addOption = event.target.closest('[data-add-option]');

            if (addOption) {
                insertRow(addOption.closest('[data-question]'), '[data-options]', '#option-template', /\[options\]\[(\d+)\]/, '__OPTION__');

                return;
            }

            const addLevel = event.target.closest('[data-add-level]');

            if (addLevel) {
                insertRow(addLevel.closest('[data-question]'), '[data-levels]', '#level-template', /\[levels\]\[(\d+)\]/, '__LEVEL__');

                return;
            }

            const removeOption = event.target.closest('[data-remove-option]');

            if (removeOption) {
                removeRow(removeOption, '[data-options]', '[data-option]', 2);

                return;
            }

            const removeLevel = event.target.closest('[data-remove-level]');

            if (removeLevel) {
                removeRow(removeLevel, '[data-levels]', '[data-level]', 2);

                return;
            }

            const button = event.target.closest('[data-remove-question]');

            if (! button) {
                return;
            }

            const list = document.querySelector('[data-questions]');

            if (list.querySelectorAll('[data-question]').length === 1) {
                return;
            }

            button.closest('[data-question]').remove();
        });

        function insertRow(question, listSelector, templateSelector, pattern, token) {
            const list = question.querySelector(listSelector);
            const named = question.querySelector('[name^="questions["]');
            const index = named?.name.match(/^questions\[([^\]]+)\]/)?.[1] ?? '0';
            let next = 0;

            list.querySelectorAll('[name]').forEach((input) => {
                const match = input.name.match(pattern);

                if (match) {
                    next = Math.max(next, Number(match[1]) + 1);
                }
            });

            list.insertAdjacentHTML(
                'beforeend',
                document.querySelector(templateSelector).innerHTML.replaceAll('__INDEX__', index).replaceAll(token, String(next)),
            );
            list.lastElementChild.querySelector('input')?.focus();
        }

        function removeRow(button, listSelector, itemSelector, minimum) {
            const list = button.closest(listSelector);

            if (list.querySelectorAll(itemSelector).length <= minimum) {
                return;
            }

            button.closest(itemSelector).remove();
        }

        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-type-select]')) {
                event.target.closest('[data-question]').dataset.type = event.target.value;

                return;
            }

            if (! event.target.matches('input[name="body_mode"]') || ! runForm || runForm.dataset.sending === 'true') {
                return;
            }

            const status = document.querySelector('[data-json-status]');

            if (event.target.value === 'json') {
                const json = formJson(runForm);

                if (json === null) {
                    status.textContent = 'Add a named question to fill this from the form.';

                    return;
                }

                const textarea = document.getElementById('body');
                textarea.value = json;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                status.textContent = 'Filled from the form.';

                return;
            }

            const error = fillForm(runForm, document.getElementById('body').value);

            if (error) {
                event.target.checked = false;
                document.querySelector('input[name="body_mode"][value="json"]').checked = true;
                status.textContent = error;
            }
        });

        function fillForm(form, source) {
            if (source.trim() === '') {
                return null;
            }

            let body;

            try {
                body = JSON.parse(source);
            } catch {
                return 'Fix the JSON syntax before switching to the form.';
            }

            if (body === null || typeof body !== 'object' || Array.isArray(body) || typeof body.questions !== 'object' || Array.isArray(body.questions)) {
                return 'Add a questions object before switching to the form.';
            }

            const list = form.querySelector('[data-questions]');
            const questionTemplate = document.querySelector('#question-template');
            let index = 0;

            list.replaceChildren();

            for (const [name, question] of Object.entries(body.questions)) {
                if (question === null || typeof question !== 'object' || Array.isArray(question)) {
                    continue;
                }

                list.insertAdjacentHTML('beforeend', questionTemplate.innerHTML.replaceAll('__INDEX__', String(index)));
                fillQuestion(list.lastElementChild, index, name, question);
                index++;
            }

            if (list.children.length === 0) {
                list.insertAdjacentHTML('beforeend', questionTemplate.innerHTML.replaceAll('__INDEX__', '0'));
            }

            const state = document.getElementById('state');

            if (typeof body.state === 'string') {
                state.value = body.state;
            } else if (body.state !== undefined && body.state !== null) {
                state.value = JSON.stringify(body.state, null, 2);
            } else {
                state.value = '';
            }

            const model = form.querySelector('#model');

            if (model && body.model && [...model.options].some((option) => option.value === body.model)) {
                model.value = body.model;
            }

            return null;
        }

        function fillQuestion(row, index, name, question) {
            const field = (suffix) => row.querySelector(`[name="questions[${index}]${suffix}"]`);
            const type = ['noul', 'choice', 'score'].includes(question.type) ? question.type : 'noul';

            field('[name]').value = name;
            field('[type]').value = type;
            field('[instructions]').value = question.instructions ?? '';
            row.dataset.type = type;

            const criteria = question.criteria ?? {};

            if (type === 'noul') {
                field('[true]').value = criteria.true ?? '';
                field('[false]').value = criteria.false ?? '';

                return;
            }

            if (type === 'choice') {
                const options = row.querySelector('[data-options]');
                options.replaceChildren();

                Object.entries(criteria).forEach(([optionName, description], optionIndex) => {
                    options.insertAdjacentHTML(
                        'beforeend',
                        document.querySelector('#option-template').innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__OPTION__', String(optionIndex)),
                    );

                    const optionRow = options.lastElementChild;
                    optionRow.querySelector(`[name="questions[${index}][options][${optionIndex}][name]"]`).value = optionName;
                    optionRow.querySelector(`[name="questions[${index}][options][${optionIndex}][description]"]`).value = description ?? '';
                });

                return;
            }

            const levels = row.querySelector('[data-levels]');
            levels.replaceChildren();

            (Array.isArray(criteria) ? criteria : []).forEach((level, levelIndex) => {
                levels.insertAdjacentHTML(
                    'beforeend',
                    document.querySelector('#level-template').innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__LEVEL__', String(levelIndex)),
                );
                levels.lastElementChild.querySelector('input').value = level;
            });
        }

        function formJson(form) {
            const values = {};

            for (const [field, value] of new FormData(form).entries()) {
                const keys = field.match(/[^\[\]]+/g);

                if (keys === null) {
                    continue;
                }

                let node = values;

                for (const key of keys.slice(0, -1)) {
                    node = node[key] ??= {};
                }

                node[keys.at(-1)] = value.trim();
            }

            const questions = {};

            for (const question of Object.values(values.questions ?? {})) {
                if (question.name) {
                    questions[question.name] = questionJson(question);
                }
            }

            if (Object.keys(questions).length === 0) {
                return null;
            }

            return JSON.stringify({ state: stateValue(values.state ?? ''), questions }, null, 2);
        }

        function questionJson(question) {
            const criteria = question.type === 'choice'
                ? Object.fromEntries(Object.values(question.options ?? {}).filter((option) => option.name).map((option) => [option.name, option.description || null]))
                : question.type === 'score'
                    ? Object.values(question.levels ?? {}).filter((level) => level !== '')
                    : question.true || question.false
                        ? { true: question.true ?? '', false: question.false ?? '' }
                        : null;

            const entry = { type: question.type, instructions: question.instructions ?? '' };

            if (criteria !== null && (Array.isArray(criteria) ? criteria.length > 0 : Object.keys(criteria).length > 0)) {
                entry.criteria = criteria;
            }

            return entry;
        }

        function stateValue(state) {
            try {
                const parsed = JSON.parse(state);

                if (parsed !== null && typeof parsed === 'object') {
                    return parsed;
                }
            } catch {}

            return state;
        }
    </script>
@endsection
