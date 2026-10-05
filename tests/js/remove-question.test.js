import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const view = readFileSync(new URL('../../resources/views/run/create.blade.php', import.meta.url), 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1];

function workspace(names = ['urgent', 'team']) {
    const listeners = {};
    const dialogListeners = {};
    const form = { dataset: {} };
    const label = { textContent: '' };
    const dialog = {
        open: false,
        addEventListener: (type, listener) => { dialogListeners[type] = listener; },
        querySelector: () => label,
        showModal() { this.open = true; },
        close() { this.open = false; dialogListeners.close(); },
    };
    const list = {
        children: [],
        querySelectorAll() { return this.children; },
    };

    list.children = names.map((name) => {
        const number = { textContent: '' };
        const input = { value: name };
        return {
            parentElement: list,
            matches: () => true,
            querySelectorAll: () => [],
            querySelector: (selector) => selector === '[data-question-number]' ? number : selector.startsWith('input') ? input : null,
            remove() { list.children.splice(list.children.indexOf(this), 1); this.parentElement = null; },
        };
    });

    const document = {
        querySelector: (selector) => ({
            '[data-run]': form,
            '[data-questions]': list,
            '[data-remove-question-dialog]': dialog,
        })[selector] ?? null,
        addEventListener: (type, listener) => { listeners[type] = listener; },
    };
    runInNewContext(script, { document });

    return {
        list, dialog, label, form,
        remove(index) {
            const button = { closest: () => list.children[index] };
            listeners.click({ target: { closest: (selector) => selector === '[data-remove-question]' ? button : null } });
        },
        confirm() {
            dialogListeners.click({ target: { closest: (selector) => selector === '[data-confirm-remove-question]' ? {} : null } });
        },
        cancel() {
            dialogListeners.click({ target: { closest: (selector) => selector === '[data-cancel-remove-question]' ? {} : null } });
        },
        backdrop() { dialogListeners.click({ target: dialog }); },
    };
}

test('removing a question waits for confirmation and renumbers remaining questions', () => {
    const page = workspace();

    page.remove(0);

    assert.equal(page.dialog.open, true);
    assert.equal(page.label.textContent, 'urgent');
    assert.equal(page.list.children.length, 2);

    page.confirm();

    assert.equal(page.dialog.open, false);
    assert.equal(page.list.children.length, 1);
    assert.equal(page.list.children[0].querySelector('[data-question-number]').textContent, '1');
});

test('cancel, backdrop, and Escape dismissal keep the question and clear pending deletion', () => {
    for (const dismiss of ['cancel', 'backdrop', 'close']) {
        const page = workspace();
        page.remove(0);

        if (dismiss === 'close') {
            page.dialog.close();
        } else {
            page[dismiss]();
        }
        page.confirm();

        assert.equal(page.dialog.open, false);
        assert.equal(page.list.children.length, 2);
    }
});

test('unnamed questions use their displayed number in the confirmation', () => {
    const page = workspace(['urgent', '']);

    page.remove(1);

    assert.equal(page.label.textContent, 'Question 2');
});

test('the last question cannot be removed', () => {
    const page = workspace(['urgent']);

    page.remove(0);

    assert.equal(page.dialog.open, false);
    assert.equal(page.list.children.length, 1);
});

test('questions cannot be removed while sending, including after opening confirmation', () => {
    const page = workspace();
    page.form.dataset.sending = 'true';

    page.remove(0);

    assert.equal(page.dialog.open, false);
    page.form.dataset.sending = 'false';
    page.remove(0);
    page.form.dataset.sending = 'true';
    page.confirm();

    assert.equal(page.list.children.length, 2);
});
