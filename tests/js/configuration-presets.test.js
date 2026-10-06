import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const view = readFileSync(new URL('../../resources/views/configuration/edit.blade.php', import.meta.url), 'utf8');

function configuration() {
    const initial = ['none', 'http://localhost:11434', 'nimble', 'http://localhost:11434'];
    const expression = view.match(/x-data="([\s\S]*?)"\s*class=/)[1]
        .replace(/{{[\s\S]*?}}/g, () => JSON.stringify(initial.shift()));
    const state = runInNewContext(`(${expression})`);
    let urlChanged;
    state.$watch = (key, callback) => { assert.equal(key, 'apiUrl'); urlChanged = callback; };
    state.$refs = { password: { value: 'typed-key' }, username: { value: 'user' }, removePassword: { checked: false } };
    state.init();
    return { state, urlChanged };
}

test('switching presets clears the model and disables choices from the old URL', () => {
    const { state, urlChanged } = configuration();
    state.applyPreset('typesafe');
    urlChanged();
    assert.equal(state.apiUrl, 'https://api.typesafe.ai');
    assert.equal(state.authType, 'bearer');
    assert.equal(state.model, '');
    assert.equal(state.$refs.password.value, '');
    assert.equal(state.$refs.username.value, '');
    assert.equal(state.$refs.removePassword.checked, true);
    assert.equal(state.credentialsChanged, true);
    assert.notEqual(state.apiUrl, state.modelsUrl);
    assert.match(view, /x-bind:disabled="apiUrl !== modelsUrl"/);
    assert.match(view, /<option x-bind:hidden="apiUrl !== modelsUrl"/);

    state.applyPreset('ollama');
    urlChanged();
    assert.equal(state.authType, 'none');
    assert.equal(state.model, '');
    assert.equal(state.apiUrl, state.modelsUrl);
});

test('editing the URL manually also clears the previous model', () => {
    const { state, urlChanged } = configuration();
    state.apiUrl = 'http://another-server:11434';
    urlChanged();
    assert.equal(state.model, '');
    assert.equal(state.$refs.password.value, '');
    assert.equal(state.$refs.username.value, '');
    assert.equal(state.$refs.removePassword.checked, true);
    assert.equal(state.credentialsChanged, true);
    assert.notEqual(state.apiUrl, state.modelsUrl);
});

test('URL input trims pasted spaces and invisible line separators', () => {
    const handler = view.match(/x-on:input="([^"]+)"/)[1];
    for (const whitespace of [' ', '\u2028', '\u2029', '\u00a0', '\r\n']) {
        const input = { value: `${whitespace}http://host.containers.internal:11434${whitespace}` };
        const context = { $el: input, apiUrl: '' };
        runInNewContext(handler, context);
        assert.equal(input.value, 'http://host.containers.internal:11434');
        assert.equal(context.apiUrl, input.value);
    }
});
