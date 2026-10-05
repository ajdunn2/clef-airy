import assert from 'node:assert/strict';
import { test } from 'node:test';
import workspace from '../../resources/js/run-workspace.js';

test('a failed new request clears the previous response and disables copying it', async () => {
    const previousFetch = globalThis.fetch;
    const previousFormData = globalThis.FormData;
    const page = workspace();
    let content = 'Previous successful response';
    page.hasResponse = true;
    page.$refs = {
        form: { action: '/run' },
        response: { replaceChildren() { content = ''; } },
    };
    globalThis.FormData = class {};
    globalThis.fetch = async () => ({
        ok: false,
        status: 422,
        json: async () => ({ errors: { body: ['Enter instructions for each question.'] } }),
    });

    try {
        await page.send();

        assert.equal(content, '');
        assert.equal(page.hasResponse, false);
        assert.equal(page.error, 'Enter instructions for each question.');
        assert.equal(page.sending, false);
    } finally {
        globalThis.fetch = previousFetch;
        globalThis.FormData = previousFormData;
    }
});
