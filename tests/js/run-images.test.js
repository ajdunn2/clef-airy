import assert from 'node:assert/strict';
import { test } from 'node:test';
import { imageKind, withinImageBudget } from '../../resources/js/run-images.js';

test('image bytes identify png, jpeg, and webp', () => {
    assert.equal(imageKind(Uint8Array.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])), 'png');
    assert.equal(imageKind(Uint8Array.from([0xff, 0xd8, 0xff, 0xe0])), 'jpeg');
    const webp = Uint8Array.from([0x52, 0x49, 0x46, 0x46, 0, 0, 0, 0, 0x57, 0x45, 0x42, 0x50]);
    assert.equal(imageKind(webp), 'webp');
    assert.equal(imageKind(Uint8Array.from([0x47, 0x49, 0x46, 0x38])), null);
});

test('image files stay within the request budget', () => {
    assert.equal(withinImageBudget(0, 1024), true);
    assert.equal(withinImageBudget(24 * 1024 * 1024, 1), false);
});
