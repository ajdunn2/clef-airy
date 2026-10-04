import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('electron', () => ({
    app: { isPackaged: true, getPath: (name: string) => `/user-data/${name}` },
}));
vi.mock('fs-extra', () => ({ default: { mkdirpSync: vi.fn(), copySync: vi.fn() } }));

import { getDefaultEnvironmentVariables } from '../src/server/php';

describe('packaged Laravel runtime caches', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('keeps runtime caches outside the signed app bundle', () => {
        vi.stubGlobal('process', { ...process, resourcesPath: '/signed-app/resources' });
        const environment = getDefaultEnvironmentVariables();

        expect(environment.APP_CONFIG_CACHE).toBe('/user-data/userData/bootstrap/cache/config.php');
        expect(environment.APP_ROUTES_CACHE).toBe('/user-data/userData/bootstrap/cache/routes-v7.php');
        expect(environment.APP_EVENTS_CACHE).toBe('/user-data/userData/bootstrap/cache/events.php');
        expect(environment.APP_PACKAGES_CACHE).toBe('/user-data/userData/bootstrap/cache/packages.php');
        expect(environment.APP_SERVICES_CACHE).toBe('/user-data/userData/bootstrap/cache/services.php');
    });
});
