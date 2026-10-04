export default () => ({
    sending: false,
    error: '',
    feedback: '',
    hasResponse: false,

    init() {
        this.$nextTick(() => {
            this.hasResponse = Boolean(this.$refs.response?.querySelector('[data-response-body]'));
        });
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
            window.dispatchEvent(new CustomEvent('response-updated'));
        } catch {
            this.error = 'The app could not complete the request. Your input has been kept; try again.';
        } finally {
            this.sending = false;
        }
    },

    async copyResponse() {
        const body = this.$refs.response.querySelector('[data-response-body]');
        if (!body) {
            return;
        }

        try {
            await navigator.clipboard.writeText(body.value);
            this.feedback = 'Response copied.';
        } catch {
            this.feedback = 'Unable to access the clipboard. Select the response text to copy it.';
        }
    },
});
