import { withSelectedModel } from './run-form-json.js';

export default () => ({
    sending: false,
    error: '',
    feedback: '',
    hasResponse: false,
    downloading: false,
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
        this.downloading = false;
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
                const errors = result.errors ?? {};
                this.error = result.errors ? Object.values(errors).flat().join('\n') : (result.message || 'The request could not be sent.');
                if (errors.path || errors.method) {
                    window.dispatchEvent(new CustomEvent('endpoint-invalid', {
                        detail: {
                            method: errors.method ?? [],
                            path: errors.path ?? [],
                        },
                    }));
                }
                return;
            }

            if (typeof result.html !== 'string') {
                throw new Error('Unexpected response');
            }

            this.$refs.response.innerHTML = result.html;
            this.hasResponse = true;
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

    async downloadExchange() {
        if (! this.hasResponse || this.downloading) {
            return;
        }

        const responseBody = this.$refs.response?.querySelector('[data-response-body]')?.value;

        if (typeof responseBody !== 'string') {
            return;
        }

        let requestBody = this.requestBody() ?? '';
        const model = document.getElementById('model')?.value;

        if (requestBody !== '' && model) {
            requestBody = withSelectedModel(requestBody, model);
        }

        const button = document.querySelector('[data-download-exchange]');
        const token = this.$refs.form?.querySelector('input[name="_token"]')?.value ?? '';
        this.downloading = true;
        this.feedback = '';

        try {
            const response = await fetch(button?.dataset.downloadUrl ?? '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    request: requestBody,
                    response: responseBody,
                }),
            });

            if (response.status === 419) {
                this.feedback = 'Your session has expired. Reload the app before downloading again.';

                return;
            }

            const disposition = response.headers.get('Content-Disposition') ?? '';

            if (response.ok && disposition.includes('attachment')) {
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'request-response.json';
                document.body.append(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);

                return;
            }

            const result = await response.json().catch(() => ({}));

            if (! response.ok) {
                const errors = result.errors ?? {};
                this.feedback = result.errors
                    ? Object.values(errors).flat().join('\n')
                    : (result.message || 'The file could not be saved.');
            }
        } catch {
            this.feedback = 'The file could not be saved.';
        } finally {
            this.downloading = false;
        }
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
