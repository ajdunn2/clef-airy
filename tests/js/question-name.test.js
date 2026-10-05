import assert from 'node:assert/strict';
import { test } from 'node:test';
import { nextDuplicateName } from '../../resources/js/question-name.js';

test('duplicating a blank question leaves the name blank', () => {
    assert.equal(nextDuplicateName('   ', ['', 'urgent']), '');
});

test('duplicating a question uses the next free copy name', () => {
    assert.equal(nextDuplicateName(' urgent ', ['urgent']), 'urgent copy');
    assert.equal(nextDuplicateName('urgent', ['urgent', 'urgent copy']), 'urgent copy 2');
    assert.equal(nextDuplicateName('urgent copy', ['urgent', 'urgent copy']), 'urgent copy 2');
    assert.equal(nextDuplicateName('urgent copy 2', ['urgent', 'urgent copy', 'urgent copy 2']), 'urgent copy 3');
    assert.equal(nextDuplicateName('support team', ['support team']), 'support team copy');
    assert.equal(nextDuplicateName('support team copy', ['support team copy']), 'support team copy 2');
});

test('a duplicated name stays within 100 characters', () => {
    const base = 'a'.repeat(100);
    const first = nextDuplicateName(base, [base]);
    const second = nextDuplicateName(base, [base, first]);

    assert.equal(first.length, 100);
    assert.equal(first.endsWith(' copy'), true);
    assert.equal(second.length <= 100, true);
    assert.equal(second.endsWith(' copy 2'), true);
    assert.notEqual(first, second);
});
