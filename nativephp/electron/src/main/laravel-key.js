import { randomBytes } from 'node:crypto';
import { chmodSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

export function initializeLaravelKey(app) {
    const directory = app.getPath('userData');
    const keyPath = join(directory, 'laravel-key.key');
    mkdirSync(directory, { recursive: true });

    if (!existsSync(keyPath)) {
        const key = `base64:${randomBytes(32).toString('base64')}`;
        try {
            writeFileSync(keyPath, key, { flag: 'wx', mode: 0o600 });
        } catch (error) {
            if (error.code !== 'EEXIST') {
                throw error;
            }
        }
    }

    chmodSync(keyPath, 0o600);
    const key = readFileSync(keyPath, 'utf8');
    if (!/^base64:[A-Za-z0-9+/]{43}=$/.test(key)) {
        throw new Error('Invalid Laravel key');
    }
    process.env.APP_KEY = key;
}
