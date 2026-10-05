import Alpine from 'alpinejs';
import runWorkspace from './run-workspace';
import { formatJson, formBodyError, formFieldValue, parseFormBody, shouldKeepExistingJson, syncFormToJson } from './run-form-json';

window.parseRunJson = parseFormBody;
window.runFieldValue = formFieldValue;
window.runFormBodyError = formBodyError;
window.serializeRunForm = (form) => syncFormToJson(new FormData(form), '', false);

Alpine.data('runWorkspace', runWorkspace);
Alpine.start();

function jsonResponses() {
    return [...document.querySelectorAll('[data-highlight-json]')].filter((element) => {
        try {
            JSON.parse(element.textContent);

            return true;
        } catch {
            return false;
        }
    });
}

const responses = jsonResponses();

const editor = document.querySelector('[data-json-editor]');

if (responses.length > 0 || editor) {
    initializeJsonHighlighting().catch((error) => {
        console.error('Unable to initialize JSON highlighting.', error);
    });
}

async function initializeJsonHighlighting() {
    const [{ createHighlighterCore }, { createJavaScriptRegexEngine }, { default: json }, { default: theme }] = await Promise.all([
        import('shiki/core'),
        import('shiki/engine/javascript'),
        import('shiki/langs/json.mjs'),
        import('shiki/themes/github-light.mjs'),
    ]);
    const highlighter = await createHighlighterCore({
        langs: [json],
        themes: [theme],
        engine: createJavaScriptRegexEngine(),
    });

    function render(source, target) {
        const tokens = highlighter.codeToTokens(source, { lang: 'json', theme: 'github-light' });
        const code = document.createElement('code');

        tokens.tokens.forEach((line, index) => {
            if (index > 0) {
                code.append(document.createTextNode('\n'));
            }

            for (const token of line) {
                const span = document.createElement('span');
                span.textContent = token.content;
                span.style.color = token.color;
                code.append(span);
            }
        });

        target.replaceChildren(code);
    }

    for (const response of responses) {
        render(response.textContent, response);
    }

    if (editor) {
        window.addEventListener('response-updated', () => {
            for (const response of jsonResponses()) {
                render(response.textContent, response);
            }
        });
        const input = editor.querySelector('textarea');
        const preview = editor.querySelector('[data-json-preview]');
        const status = document.querySelector('[data-json-status]');
        let frame;

        function synchronizeScroll() {
            preview.scrollTop = input.scrollTop;
            preview.scrollLeft = input.scrollLeft;
        }

        function updatePreview() {
            try {
                render(`${input.value}\n`, preview);
                editor.dataset.highlighted = 'true';
                synchronizeScroll();
            } catch {
                delete editor.dataset.highlighted;
                preview.replaceChildren();
            }
        }

        updatePreview();
        input.addEventListener('input', () => {
            status.textContent = '';
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(updatePreview);
        });
        input.addEventListener('scroll', synchronizeScroll);

        document.querySelector('[data-format-json]').addEventListener('click', () => {
            const result = formatJson(input.value);

            if (result.ok) {
                input.setRangeText(result.json, 0, input.value.length, 'start');
                updatePreview();
                status.textContent = 'JSON formatted.';
                status.classList.add('text-muted');
                status.classList.remove('text-red-700');
            } else {
                status.textContent = result.message;
                status.classList.add('text-red-700');
                status.classList.remove('text-muted');
            }

            input.focus();
        });

        window.addEventListener('pagehide', (event) => {
            if (! event.persisted) {
                highlighter.dispose();
            }
        });
    } else {
        highlighter.dispose();
    }
}

const runForm = document.querySelector('[data-run]');
const jsonBody = document.getElementById('body');
const jsonStatus = document.querySelector('[data-json-status]');

if (runForm && jsonBody && jsonStatus) {
    const loadedAsJson = runForm.querySelector('input[name="body_mode"]:checked')?.value === 'json';
    let jsonEdited = shouldKeepExistingJson(new FormData(runForm), jsonBody.value, loadedAsJson);
    let suppressJsonEdited = false;

    const showJsonStatus = (message, danger) => {
        jsonStatus.textContent = message;
        jsonStatus.classList.toggle('text-red-700', danger);
        jsonStatus.classList.toggle('text-muted', ! danger);
    };

    jsonBody.addEventListener('input', () => {
        if (suppressJsonEdited) {
            return;
        }

        jsonEdited = true;
        showJsonStatus('', false);
    });

    runForm.querySelectorAll('input[name="body_mode"]').forEach((radio) => {
        radio.addEventListener('change', (event) => {
            if (runForm.dataset.sending === 'true') {
                return;
            }

            if (event.target.value !== 'json') {
                showJsonStatus('', false);

                return;
            }

            const result = syncFormToJson(new FormData(runForm), jsonBody.value, jsonEdited);

            if (result.action === 'replace') {
                suppressJsonEdited = true;
                jsonBody.value = result.json;
                jsonBody.dispatchEvent(new Event('input', { bubbles: true }));
                suppressJsonEdited = false;
                jsonEdited = false;
            }

            showJsonStatus(result.message, result.tone === 'danger');
        });
    });
}
