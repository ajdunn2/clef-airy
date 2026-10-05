import { withSelectedModel } from './run-form-json.js';

export default () => ({
    sending: false,
    error: '',
    feedback: '',
    hasResponse: false,
    copied: '',

    init() {
        this.$nextTick(() => {
            this.hasResponse = Boolean(this.$refs.response?.querySelector('[data-response-body]'));

            if (this.$refs.bookmarkDialog?.hasAttribute('data-open')) {
                this.openBookmark();
            }
        });
    },

    openBookmark() {
        const dialog = this.$refs.bookmarkDialog;

        if (dialog && ! dialog.open) {
            dialog.showModal();
        }

        this.$nextTick(() => {
            this.$refs.bookmarkName?.focus();
            this.$refs.bookmarkName?.select();
        });
    },

    submit(event) {
        if (event.submitter?.hasAttribute('data-save')) {
            return;
        }

        event.preventDefault();
        this.send();
    },

    shortcut(event) {
        if ((event.metaKey || event.ctrlKey) && event.key === 'Enter' && !event.isComposing) {
            event.preventDefault();
            if (!this.sending) {
                this.$refs.form?.requestSubmit();
            }
        }
    },

    async send() {
        if (this.sending) {
            return;
        }

        const form = this.$refs.form;
        const body = new FormData(form);
        this.sending = true;
        this.error = '';
        this.feedback = '';
        this.hasResponse = false;
        this.$refs.response.replaceChildren();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });

            if (response.status === 419) {
                this.error = 'Your session has expired. Reload the app before sending again.';
                return;
            }

            const result = await response.json();
            if (!response.ok) {
                this.error = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'The request could not be sent.');
                return;
            }

            if (typeof result.html !== 'string') {
                throw new Error('Unexpected response');
            }

            this.$refs.response.innerHTML = result.html;
            this.hasResponse = true;
            this.feedback = 'Response received.';
            this.$refs.response.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            window.dispatchEvent(new CustomEvent('response-updated'));
        } catch {
            this.error = 'The app could not complete the request. Your input has been kept; try again.';
        } finally {
            this.sending = false;
        }
    },

    showCopied(name) {
        clearTimeout(this.copyTimer);
        this.copied = name;
        this.copyTimer = setTimeout(() => {
            this.copied = '';
        }, 2000);
    },

    async copyResponse() {
        const body = this.$refs.response.querySelector('[data-response-body]');
        if (!body) {
            return;
        }

        if (! await this.copyText(body.value)) {
            this.feedback = 'Unable to access the clipboard. Select the response text to copy it.';

            return;
        }

        this.showCopied('response');
    },

    async copyRequest() {
        let source = this.requestBody();

        if (source === null) {
            this.feedback = 'Nothing to copy yet.';

            return;
        }

        const model = document.getElementById('model')?.value;

        if (model) {
            source = withSelectedModel(source, model);
        }

        if (! await this.copyText(source)) {
            this.feedback = 'Unable to access the clipboard. Copy it from the JSON view instead.';

            return;
        }

        this.showCopied('request');
    },

    requestBody() {
        if (document.querySelector('input[name="body_mode"][value="json"]:checked')) {
            const body = document.getElementById('body')?.value ?? '';

            return body.trim() === '' ? null : body;
        }

        return this.$refs.form ? formJson(this.$refs.form) : null;
    },

    async copyText(text) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            return copyWithEditableRegion(text);
        }
    },
});

function copyWithEditableRegion(text) {
    const helper = document.createElement('textarea');
    helper.value = text;
    helper.setAttribute('readonly', '');
    helper.style.position = 'fixed';
    helper.style.opacity = '0';
    document.body.append(helper);
    helper.select();
    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    helper.remove();

    return copied;
}
