import Alpine from 'alpinejs';
import runWorkspace from './run-workspace';

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
            try {
                const formatted = JSON.stringify(JSON.parse(input.value), null, 2);
                input.setRangeText(formatted, 0, input.value.length, 'start');
                updatePreview();
                status.textContent = 'JSON formatted.';
            } catch {
                status.textContent = 'Fix the JSON syntax before formatting.';
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
