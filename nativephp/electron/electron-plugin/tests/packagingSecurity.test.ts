import { execFile } from 'child_process';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('child_process', () => ({ execFile: vi.fn() }));

const originalArguments = process.argv;

async function builder(release = false) {
    vi.resetModules();
    vi.stubEnv('APP_PATH', '/fake/project');
    vi.stubEnv('NATIVEPHP_RELEASE', release ? 'true' : 'false');
    vi.stubEnv('NATIVEPHP_APPLE_ID', '');
    vi.stubEnv('NATIVEPHP_APPLE_ID_PASS', '');
    vi.stubEnv('NATIVEPHP_APPLE_TEAM_ID', '');
    vi.stubEnv('CSC_NAME', '');
    vi.stubEnv('CSC_LINK', '');
    process.argv = [...originalArguments, '--mac'];
    return (await import('../../electron-builder.mjs')).default;
}

describe('macOS packaging security', () => {
    afterEach(() => {
        process.argv = originalArguments;
        vi.unstubAllEnvs();
        vi.clearAllMocks();
    });

    it('refuses a public release without signing and notarization credentials', async () => {
        await expect(builder(true)).rejects.toThrow('macOS releases require a Developer ID certificate');
    });

    it('allows a local build with ad-hoc signing', async () => {
        const config = await builder();
        expect(config.mac.identity).toBe('-');
        expect(config.mac.entitlements).toBe('build/entitlements.local.mac.plist');
    });

    it('waits for PHP preparation before allowing packaging to continue', async () => {
        const config = await builder();
        let complete = false;
        const pending = config.beforePack({ arch: 3 }).then(() => {
            complete = true;
        });
        await Promise.resolve();
        expect(complete).toBe(false);
        const callback = vi.mocked(execFile).mock.calls[0].at(-1) as Function;
        callback(null, { stdout: '', stderr: '' });
        await pending;
        expect(complete).toBe(true);
    });

    it('stops packaging when PHP preparation fails', async () => {
        const config = await builder();
        const pending = config.beforePack({ arch: 3 });
        const failure = expect(pending).rejects.toThrow('PHP extraction failed');
        const callback = vi.mocked(execFile).mock.calls[0].at(-1) as Function;
        callback(new Error('PHP extraction failed'));
        await failure;
    });
});
