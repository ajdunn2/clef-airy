import { mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('electron', () => ({ safeStorage: {} }));

import { initializeLaravelKey } from '../../src/main/laravel-key.js';

describe('per-installation Laravel key', () => {
    const directories: string[] = [];

    function application() {
        const directory = mkdtempSync(join(tmpdir(), 'clef-airy-key-'));
        directories.push(directory);
        return { getPath: () => directory };
    }

    afterEach(() => {
        for (const directory of directories.splice(0)) rmSync(directory, { recursive: true, force: true });
        vi.unstubAllEnvs();
    });

    it('reuses a private local key across restarts without Keychain access', () => {
        vi.stubEnv('APP_KEY', 'development-key');
        const app = application();
        initializeLaravelKey(app);
        const key = process.env.APP_KEY;
        const path = join(app.getPath(), 'laravel-key.key');
        expect(key).toMatch(/^base64:[A-Za-z0-9+/]{43}=$/);
        expect(readFileSync(path, 'utf8')).toBe(key);
        expect(statSync(path).mode & 0o777).toBe(0o600);

        initializeLaravelKey(app);
        expect(process.env.APP_KEY).toBe(key);
    });

    it('does not reuse a bundled key for a different installation', () => {
        vi.stubEnv('APP_KEY', 'development-key');
        initializeLaravelKey(application());
        const firstKey = process.env.APP_KEY;
        initializeLaravelKey(application());
        expect(process.env.APP_KEY).not.toBe(firstKey);
        expect(process.env.APP_KEY).not.toBe('development-key');
    });

    it('leaves the previous Keychain-encrypted key intact without opening it', () => {
        const app = application();
        const path = join(app.getPath(), 'laravel-key.enc');
        writeFileSync(path, 'previous-encrypted-key');
        initializeLaravelKey(app);
        expect(readFileSync(path, 'utf8')).toBe('previous-encrypted-key');
    });

    it('preserves a corrupted local key instead of replacing it', () => {
        const app = application();
        const path = join(app.getPath(), 'laravel-key.key');
        writeFileSync(path, 'corrupted');
        expect(() => initializeLaravelKey(app)).toThrow('Invalid Laravel key');
        expect(readFileSync(path, 'utf8')).toBe('corrupted');
    });
});
