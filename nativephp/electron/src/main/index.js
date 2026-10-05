import NativePHP from '#plugin';
import { app, dialog } from 'electron';
import path from 'path';
import { initializeLaravelKey } from './laravel-key.js';
import { createSplash } from './splash.js';
// Inherit User's PATH in Process & ChildProcess
import fixPath from 'fix-path';
fixPath();

const buildPath = path.resolve(import.meta.dirname, import.meta.env.MAIN_VITE_NATIVEPHP_BUILD_PATH);
const defaultIcon = path.join(buildPath, 'icon.png');
const certificate = path.join(buildPath, 'cacert.pem');

const executable = process.platform === 'win32' ? 'php.exe' : 'php';
const phpBinary = path.join(buildPath, 'php', executable);
const appPath = path.join(buildPath, 'app');

let splashWindow;

app.whenReady().then(() => {
    try {
        initializeLaravelKey(app);
    } catch {
        dialog.showErrorBox(
            'Encryption key unavailable',
            'Clef Airy Decisions API Tester could not read its local encryption key. Check permissions for its application-data folder.',
        );
        app.quit();
        return;
    }
    try {
        splashWindow = createSplash(appPath, import.meta.dirname);
    } catch (error) {
        console.error('Error creating splash screen:', error);
    }

    NativePHP.bootstrap(app, defaultIcon, phpBinary, certificate, appPath);
});

app.on('browser-window-created', (event, window) => {
    if (splashWindow && window !== splashWindow) {
        window.webContents.on('did-navigate', (evt, url) => {
            if (url.startsWith('http://127.0.0.1') || url.startsWith('http://localhost')) {
                if (splashWindow) {
                    splashWindow.close();
                    splashWindow = null;
                }
            }
        });
    }
});
