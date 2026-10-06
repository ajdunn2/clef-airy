@extends('layouts.app')

@section('title', $pageTitle.' — Clef Airy Decisions API Tester')

@section('heading', $pageTitle)

@section('actions')
    @if ($configured)
        <button type="submit" form="run-form" data-send :disabled="sending" class="inline-flex min-w-24 items-center justify-center gap-1 rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90 disabled:cursor-progress disabled:opacity-70"><x-lucide-send class="size-4 shrink-0" aria-hidden="true" /><span x-text="sending ? 'Sending…' : 'Send'">Send</span></button>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <p class="text-[13px] text-muted" role="status">{{ session('status') }}</p>
    @endif

    <div class="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    @if ($configured)
        <form id="run-form" method="POST" action="{{ route($storeRoute) }}" data-run x-ref="form" x-on:submit="submit($event)" :aria-busy="sending" :data-sending="sending" class="grid min-w-0 gap-4">
            @csrf
            <fieldset :disabled="sending" class="grid min-w-0 gap-4">

            <div class="instrument-panel grid gap-4 rounded-xl border border-line bg-white p-5">
                <div class="grid gap-1.5">
                    <label for="model" class="instrument-heading technical-label">Model</label>
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

                <div
                    class="grid gap-3"
                    x-data="{
                        editing: {{ $errors->has('method') || $errors->has('path') ? 'true' : 'false' }},
                        method: @js(old('method', $prefill['method'])),
                        path: @js(old('path', $prefill['path'])),
                        methodError: '',
                        pathError: '',
                        open(event) {
                            const detail = event?.detail;
                            if (detail && (detail.method || detail.path)) {
                                this.methodError = [].concat(detail.method ?? []).join('\n');
                                this.pathError = [].concat(detail.path ?? []).join('\n');
                            }
                            this.editing = true;
                            this.focusWhenReady(this.$refs.path);
                        },
                        close() {
                            this.editing = false;
                            this.focusWhenReady(this.$refs.edit);
                        },
                        focusWhenReady(element) {
                            let remaining = 20;
                            const attempt = () => {
                                const ready = element.isConnected
                                    && ! element.disabled
                                    && getComputedStyle(element).display !== 'none'
                                    && element.getClientRects().length > 0;
                                if (ready) {
                                    element.focus();
                                    return;
                                }
                                if (remaining-- > 0) {
                                    requestAnimationFrame(attempt);
                                }
                            };
                            requestAnimationFrame(attempt);
                        },
                    }"
                    x-on:endpoint-invalid.window="open($event)"
                >
                    <div x-show="! editing" class="flex min-w-0 items-center gap-2">
                        <p class="flex min-w-0 flex-1 items-center gap-2 text-[13px]">
                            <span class="inline-flex shrink-0 items-center rounded-full bg-ink/8 px-2 py-0.5 text-xs font-medium text-ink" x-text="method">{{ old('method', $prefill['method']) }}</span>
                            <code class="min-w-0 truncate rounded bg-canvas px-1.5 py-0.5 font-mono" :class="path.trim() === '' ? 'text-muted' : ''" :title="path" x-text="path.trim() === '' ? 'Enter a path' : path">{{ old('path', $prefill['path']) !== '' ? old('path', $prefill['path']) : 'Enter a path' }}</code>
                        </p>
                        <button type="button" x-ref="edit" x-on:click="open()" aria-label="Edit endpoint" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-[13px] font-medium text-muted hover:bg-canvas hover:text-ink">
                            <x-lucide-pencil class="size-3.5 shrink-0" aria-hidden="true" />
                            Edit
                        </button>
                    </div>

                    <div x-show="editing" x-cloak x-on:keydown.escape="close()" class="grid gap-3">
                        <div class="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
                            <div class="grid gap-1.5">
                                <label for="method" class="text-[13px] font-medium">Method</label>
                                <select id="method" name="method" x-model="method" x-on:change="methodError = ''" class="rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                                        <option value="{{ $method }}" @selected(old('method', $prefill['method']) === $method)>{{ $method }}</option>
                                    @endforeach
                                </select>
                                @error('method')
                                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="whitespace-pre-line text-[13px] text-red-700" x-cloak x-show="methodError" x-text="methodError"></p>
                            </div>

                            <div class="grid gap-1.5">
                                <label for="path" class="text-[13px] font-medium">Path</label>
                                <input
                                    id="path"
                                    name="path"
                                    type="text"
                                    x-model="path"
                                    x-ref="path"
                                    x-on:input="pathError = ''"
                                    value="{{ old('path', $prefill['path']) }}"
                                    class="rounded-lg border border-line bg-field px-3 py-2 font-mono text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                                    placeholder="/v1/systemone"
                                >
                                @error('path')
                                    <p class="text-[13px] text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="whitespace-pre-line text-[13px] text-red-700" x-cloak x-show="pathError" x-text="pathError"></p>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" x-on:click="close()" class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Done</button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="instrument-panel grid gap-4 rounded-xl border border-line bg-white p-5">
                <div data-switch class="grid gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="instrument-heading technical-label">Request</span>
                        <button type="button" x-on:click="openBookmark" class="inline-flex items-center gap-1.5 rounded-lg border border-accent/30 bg-accent/10 px-3 py-1.5 text-[13px] font-semibold text-accent transition-colors hover:border-accent/50 hover:bg-accent/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2"><x-lucide-bookmark class="size-4 shrink-0" aria-hidden="true" />Bookmark</button>
                        <div class="ml-auto flex rounded-lg bg-canvas p-0.5 text-[13px]">
                            <label class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-muted">
                                <input type="radio" name="body_mode" value="form" data-pick="form" class="sr-only" @checked(old('body_mode', $prefill['body_mode']) === 'form')>
                                <x-lucide-form class="size-3.5 shrink-0" aria-hidden="true" />
                                Form
                            </label>
                            <label class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-muted">
                                <input type="radio" name="body_mode" value="json" data-pick="json" class="sr-only" @checked(old('body_mode', $prefill['body_mode']) === 'json')>
                                <x-lucide-braces class="size-3.5 shrink-0" aria-hidden="true" />
                                JSON
                            </label>
                        </div>
                    </div>

                    <div data-panel="form" class="grid gap-3">
                        <div class="grid gap-1.5">
                            <label for="state" class="text-[13px] font-medium">State</label>
                            <textarea id="state" name="state" rows="3" autofocus class="field-sizing-content min-h-20 w-full rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">{{ old('state', $prefill['state']) }}</textarea>
                            @error('state')
                                <p class="text-[13px] text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-3" data-questions>
                            @foreach (old('questions', $prefill['questions']) as $index => $question)
                                @include('run.question', ['index' => $index, 'question' => $question])
                            @endforeach
                        </div>

                        @error('questions')
                            <p class="text-[13px] text-red-700">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" data-add-question class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium disabled:opacity-40"><x-lucide-message-square-plus class="size-4 shrink-0" aria-hidden="true" />Add question</button>
                            <button type="button" data-duplicate-question class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium disabled:opacity-40"><x-lucide-book-copy class="size-4 shrink-0" aria-hidden="true" />Duplicate</button>
                        </div>
                    </div>

                    <div data-panel="json" class="grid gap-1.5">
                        <label for="body" class="text-[13px] font-medium">JSON</label>
                        <div data-json-editor class="relative grid overflow-hidden rounded-lg border border-line bg-field focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/25">
                            <pre data-json-preview aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden whitespace-pre p-0 px-3 py-2 font-mono text-[13px] leading-5"></pre>
                            <textarea id="body" name="body" rows="16" wrap="off" spellcheck="false" class="relative w-full resize-y bg-transparent px-3 py-2 font-mono text-[13px] leading-5 outline-none">{{ old('body', $prefill['body']) }}</textarea>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" data-format-json class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Format JSON</button>
                            <span data-json-status role="status" class="text-[13px] text-muted"></span>
                        </div>
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
                            <input name="questions[__INDEX__][options][__OPTION__][name]" value="" placeholder="Name" aria-label="Option name" class="w-full rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                            <input name="questions[__INDEX__][options][__OPTION__][description]" value="" placeholder="Description" aria-label="Option description" class="w-full rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        </div>
                        <button type="button" data-remove-option class="rounded-lg px-2.5 py-2 text-muted hover:bg-white hover:text-ink" aria-label="Remove option"><x-lucide-minus class="size-3" aria-hidden="true" /></button>
                    </div>
                </template>

                <template id="level-template">
                    <div class="flex items-center gap-2" data-level>
                        <input name="questions[__INDEX__][levels][__LEVEL__]" value="" placeholder="Level" aria-label="Score level" class="w-full rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        <button type="button" data-remove-level class="rounded-lg px-2.5 py-2 text-muted hover:bg-white hover:text-ink" aria-label="Remove level"><x-lucide-minus class="size-3" aria-hidden="true" /></button>
                    </div>
                </template>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <span x-show="! sending" class="text-[13px] text-muted">Ctrl/⌘⏎ to send</span>
                    <button type="submit" data-send class="inline-flex items-center gap-1 rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90"><x-lucide-send class="size-4 shrink-0" aria-hidden="true" /><span x-text="sending ? 'Sending…' : 'Send'">Send</span></button>
                </div>
            </div>
            </fieldset>
        </form>
    @else
        <div class="rounded-xl border border-line bg-white p-5">
            <p class="text-[13px] text-ink/80">Save the API URL before sending a request.</p>
            <a href="{{ route('configuration.edit') }}" class="mt-3 inline-block text-[13px] font-medium text-accent">Go to Configuration</a>
        </div>
    @endif

    <section class="instrument-panel min-w-0 self-start rounded-xl border border-line bg-white p-5 lg:sticky lg:top-0" aria-label="Response" :aria-busy="sending">
        <h2 class="mb-4 instrument-heading technical-label">Response</h2>
        <div class="mb-3 flex min-h-8 flex-wrap items-center justify-between gap-3">
            <p role="status" class="text-[13px] text-muted" x-text="sending ? 'Sending request…' : feedback"></p>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="copyRequest" :disabled="sending" :class="copied === 'request' ? 'border-accent text-accent' : 'border-line'" class="inline-flex min-w-27 items-center justify-center gap-1 rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors duration-300 disabled:opacity-40"><x-lucide-copy class="size-4 shrink-0" aria-hidden="true" /><span x-text="copied === 'request' ? 'Copied' : 'Copy request'">Copy request</span></button>
                <button type="button" x-on:click="copyResponse" :disabled="!hasResponse" :class="copied === 'response' ? 'border-accent text-accent' : 'border-line'" class="inline-flex min-w-30 items-center justify-center gap-1 rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors duration-300 disabled:opacity-40"><x-lucide-copy class="size-4 shrink-0" aria-hidden="true" /><span x-text="copied === 'response' ? 'Copied' : 'Copy response'">Copy response</span></button>
            </div>
        </div>
        <p x-cloak x-show="error" x-text="error" role="alert" class="mb-3 whitespace-pre-line text-[13px] text-red-700"></p>
        <div class="grid gap-3">
            <div x-ref="response" class="min-w-0">
                @if (is_array($result))
                    @include('run.response', ['result' => $result])
                @else
                    <div class="flex min-h-64 items-end border border-line bg-canvas p-4"><p class="font-mono text-xs text-muted">Awaiting response</p></div>
                @endif
            </div>
            <div x-cloak x-show="hasResponse" class="flex justify-end">
                <button type="button" data-download-exchange data-download-url="{{ route('run.download') }}" x-on:click="downloadExchange" :disabled="downloading" class="inline-flex min-w-36 items-center justify-center gap-1 rounded-lg border border-line px-3 py-1.5 text-[13px] font-medium disabled:opacity-40"><x-lucide-download class="size-4 shrink-0" aria-hidden="true" /><span x-text="downloading ? 'Saving…' : 'Download JSON'">Download JSON</span></button>
            </div>
        </div>
    </section>
    </div>

    @if ($configured)
        <dialog
            data-remove-question-dialog
            aria-labelledby="remove-question-title"
            class="m-auto w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-white p-4 text-ink shadow-lg shadow-black/10 backdrop:bg-ink/30"
        >
            <div class="grid gap-3">
                <h2 id="remove-question-title" class="text-sm font-semibold">Remove question</h2>
                <p class="text-[13px]">Remove <span data-remove-question-name class="font-medium" style="overflow-wrap:anywhere"></span>?</p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" data-cancel-remove-question autofocus class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Cancel</button>
                    <button type="button" data-confirm-remove-question class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90">Remove</button>
                </div>
            </div>
        </dialog>

        <dialog
            x-ref="bookmarkDialog"
            x-on:click="if ($event.target === $el) $el.close()"
            aria-labelledby="bookmark-title"
            class="m-auto w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-white p-4 text-ink shadow-lg shadow-black/10 backdrop:bg-ink/30"
            @if ($errors->has('name')) data-open @endif
        >
            <div class="grid gap-3">
                <h2 id="bookmark-title" class="text-sm font-semibold">Bookmark</h2>
                <div class="grid gap-1.5">
                    <label for="bookmark-name" class="text-[13px] font-medium">Name</label>
                    <input
                        id="bookmark-name"
                        x-ref="bookmarkName"
                        form="run-form"
                        name="name"
                        value="{{ old('name', $prefill['name']) }}"
                        x-on:keydown.enter.prevent="$refs.bookmarkSubmit.click()"
                        class="w-full rounded-lg border border-line bg-field px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"
                    >
                    @error('name')
                        <p class="text-[13px] text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" x-on:click="$refs.bookmarkDialog.close()" class="rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium">Cancel</button>
                    <button
                        type="submit"
                        x-ref="bookmarkSubmit"
                        form="run-form"
                        data-save
                        formaction="{{ route('calls.store') }}"
                        :disabled="sending"
                        class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-medium text-white hover:bg-accent/90"
                    >Bookmark</button>
                </div>
            </div>
        </dialog>
    @endif

    <script>
        const runForm = document.querySelector('[data-run]');
        const removeQuestionDialog = document.querySelector('[data-remove-question-dialog]');
        let pendingQuestion = null;

        removeQuestionDialog?.addEventListener('close', () => {
            pendingQuestion = null;
        });

        removeQuestionDialog?.addEventListener('click', (event) => {
            if (event.target === removeQuestionDialog || event.target.closest('[data-cancel-remove-question]')) {
                removeQuestionDialog.close();

                return;
            }

            if (! event.target.closest('[data-confirm-remove-question]')) {
                return;
            }

            const list = document.querySelector('[data-questions]');

            if (runForm?.dataset.sending !== 'true' && pendingQuestion?.parentElement === list && list.querySelectorAll('[data-question]').length > 1) {
                pendingQuestion.remove();
                renumberQuestions(list);
            }

            removeQuestionDialog.close();
        });

        document.querySelector('[data-add-question]')?.addEventListener('click', () => {
            if (runForm?.dataset.sending === 'true') {
                return;
            }

            const list = document.querySelector('[data-questions]');

            if (list.querySelectorAll('[data-question]').length >= 64) {
                return;
            }

            const template = document.querySelector('#question-template');
            const indexes = [...list.querySelectorAll('[name^="questions["]')].map((input) => Number(input.name.match(/^questions\[(\d+)\]/)?.[1] ?? -1));
            const index = Math.max(-1, ...indexes) + 1;

            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            renumberQuestions(list);
        });

        document.querySelector('[data-duplicate-question]')?.addEventListener('click', () => {
            if (runForm?.dataset.sending === 'true') {
                return;
            }

            const list = document.querySelector('[data-questions]');
            const rows = [...list.children].filter((row) => row.matches('[data-question]'));

            if (rows.length === 0 || rows.length >= 64) {
                return;
            }

            const source = rows.at(-1);
            const copy = source.cloneNode(true);
            const sourceFields = [...source.querySelectorAll('input, select, textarea')];

            [...copy.querySelectorAll('input, select, textarea')].forEach((field, index) => {
                const original = sourceFields[index];

                if (! original) {
                    return;
                }

                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = original.checked;
                } else {
                    field.value = original.value;
                }
            });

            const name = [...copy.querySelectorAll('input')].find((input) => /^questions\[[^\]]+\]\[name\]$/.test(input.name));

            if (name) {
                const existing = [...list.querySelectorAll('input')]
                    .filter((input) => /^questions\[[^\]]+\]\[name\]$/.test(input.name))
                    .map((input) => input.value);
                name.value = window.nextDuplicateName(name.value, existing);
            }

            const type = copy.querySelector('[data-type-select]');

            if (type) {
                copy.dataset.type = type.value;
            }

            list.append(copy);
            renumberQuestions(list);
            name?.focus();
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

            const move = event.target.closest('[data-move-question]');

            if (move) {
                moveQuestion(move);

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

            pendingQuestion = button.closest('[data-question]');
            removeQuestionDialog.querySelector('[data-remove-question-name]').textContent = pendingQuestion.querySelector('input[name$="[name]"]').value.trim()
                || `Question ${pendingQuestion.querySelector('[data-question-number]').textContent}`;
            removeQuestionDialog.showModal();
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
                body = window.parseRunJson(source);
            } catch {
                return 'Fix the JSON syntax before switching to the form.';
            }

            const bodyError = window.runFormBodyError(body);

            if (bodyError) {
                return bodyError;
            }

            const model = form.querySelector('#model');

            if (body.model && model && ! [...model.options].some((option) => option.value === body.model)) {
                return 'Choose this model in Configuration first, or keep using the JSON view.';
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
                state.value = window.runFieldValue(body.state);
            } else {
                state.value = '';
            }

            if (model && body.model && [...model.options].some((option) => option.value === body.model)) {
                model.value = body.model;
            }

            renumberQuestions(list);

            return null;
        }

        function fillQuestion(row, index, name, question) {
            const field = (suffix) => row.querySelector(`[name="questions[${index}]${suffix}"]`);
            const type = ['noul', 'choice', 'score'].includes(question.type) ? question.type : 'noul';

            field('[name]').value = name;
            field('[type]').value = type;
            field('[instructions]').value = Object.hasOwn(question, 'instructions') ? window.runFieldValue(question.instructions) : '';
            row.dataset.type = type;

            const criteria = question.criteria ?? {};

            if (type === 'noul') {
                field('[true]').value = Object.hasOwn(criteria, 'true') ? window.runFieldValue(criteria.true) : '';
                field('[false]').value = Object.hasOwn(criteria, 'false') ? window.runFieldValue(criteria.false) : '';

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
                    optionRow.querySelector(`[name="questions[${index}][options][${optionIndex}][description]"]`).value = description === null ? '' : window.runFieldValue(description);
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
                levels.lastElementChild.querySelector('input').value = window.runFieldValue(level);
            });
        }

        function formJson(form) {
            const result = window.serializeRunForm(form);

            if (result.action !== 'replace') {
                document.querySelector('[data-json-status]').textContent = result.message;

                return null;
            }

            return result.json;
        }

        const questionSlides = new WeakMap();

        function moveQuestion(button) {
            const row = button.closest('[data-question]');
            const list = row?.parentElement;
            const sibling = button.dataset.moveQuestion === 'up'
                ? row?.previousElementSibling
                : row?.nextElementSibling;

            if (! row || ! list || ! sibling?.matches('[data-question]')) {
                return;
            }

            const starts = new Map([row, sibling].map((card) => [card, card.getBoundingClientRect()]));

            for (const card of starts.keys()) {
                delete card.dataset.sliding;
                card.style.transform = '';
            }

            if (button.dataset.moveQuestion === 'up') {
                list.insertBefore(row, sibling);
            } else {
                list.insertBefore(sibling, row);
            }

            renumberQuestions(list);

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const token = {};

            for (const [card, start] of starts) {
                const delta = start.top - card.getBoundingClientRect().top;

                if (delta !== 0) {
                    card.style.transform = `translateY(${delta}px)`;
                    questionSlides.set(card, token);
                }
            }

            row.offsetHeight;

            for (const card of starts.keys()) {
                if (questionSlides.get(card) !== token) {
                    continue;
                }

                card.dataset.sliding = card === row ? 'moved' : '';
                card.style.transform = '';
                window.setTimeout(() => {
                    if (questionSlides.get(card) !== token) {
                        return;
                    }

                    delete card.dataset.sliding;
                    questionSlides.delete(card);
                }, 320);
            }
        }

        function renumberQuestions(list) {
            const rows = [...list.children].filter((row) => row.matches('[data-question]'));

            rows.forEach((row, index) => {
                row.querySelectorAll('[name^="questions["]').forEach((input) => {
                    input.name = input.name.replace(/^questions\[[^\]]+\]/, `questions[${index}]`);
                });
                row.querySelectorAll('label[for]').forEach((label) => {
                    label.htmlFor = label.htmlFor.replace(/-\d+$/, `-${index}`);
                });
                row.querySelectorAll('[id^="question-"]').forEach((field) => {
                    field.id = field.id.replace(/-\d+$/, `-${index}`);
                });

                const up = row.querySelector('[data-move-question="up"]');
                const down = row.querySelector('[data-move-question="down"]');
                const number = row.querySelector('[data-question-number]');

                if (number) {
                    number.textContent = String(index + 1);
                }

                if (up) {
                    up.disabled = index === 0;
                }

                if (down) {
                    down.disabled = index === rows.length - 1;
                }
            });

            const add = document.querySelector('[data-add-question]');
            const duplicate = document.querySelector('[data-duplicate-question]');

            if (add) {
                add.disabled = rows.length >= 64;
            }

            if (duplicate) {
                duplicate.disabled = rows.length === 0 || rows.length >= 64;
            }
        }

        const questionList = document.querySelector('[data-questions]');

        if (questionList) {
            renumberQuestions(questionList);
        }
    </script>
@endsection
