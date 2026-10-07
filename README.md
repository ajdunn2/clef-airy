# Clef Airy Decisions API Tester

A desktop app for sending requests to Ollama System One, Jev, and Cloudflare Workers AI decision APIs.

## Install and run

Download your installer from [GitHub Releases](https://github.com/ajdunn2/clef-airy/releases/):

- **Mac Apple Silicon (M-series):** `*-arm64.dmg`
- **Mac Intel:** `*-x64.dmg`
- **Windows:** `*-setup.exe`

### macOS

Open the `.dmg`, drag **Clef Airy Decisions API Tester** into **Applications**, then open it.

If macOS blocks it, run this in Terminal, then open the app again:

```bash
xattr -dr com.apple.quarantine '/Applications/Clef Airy Decisions API Tester.app'
```

### Windows

Run the `.exe` installer, then open **Clef Airy Decisions API Tester** from the Start menu.

In the app, save your API URL and model in **Configuration**, then open **Compose a request**. For local Ollama, make sure Ollama is running first.

## Configure your API and send a request

In **Configuration**, enter your API URL, choose a model, and select an authentication method: None, Basic, Bearer, or Cloudflare Workers AI. Presets for Local Ollama, TypeSafe AI, and Cloudflare Workers AI fill in the URL and authentication for you.

For [Jev](https://docs.typesafe.ai/api), use `https://api.typesafe.ai`, select `jev-latest`, and choose Bearer authentication. Enter your TypeSafe key in **Password / API key**.

In **Compose a request**, describe a situation and add yes/no, choice, or score questions. Score questions allow up to 26 levels. Edit the request using the form or JSON, then view the response in Easy or JSON mode. State and instructions can be plain text or JSON objects and arrays. Criteria entered on the form are sent as plain text. Criteria already written in the JSON are left as written.

When the selected model can read images, you can attach PNG, JPEG, or WebP files by dropping them in or choosing them. Clef, Clef Flash, and Ollama models that report vision show the image control. Thumbnails show the files, and you can reorder or remove them. The images are sent with the state. Ollama receives raw base64. Clef and Clef Flash on Workers AI receive data URIs. Attached images must fit in a 32 MB request. Workers AI also limits Clef and Clef Flash to 4 images and a 13 MB body.

Below each response, the app shows token usage, request duration, and the number of questions sent, each with an icon. You can also download the request and response as JSON (including any attached images).

In **Configuration**, set the yes/no decision threshold (default 50%). It changes the displayed yes/no result only, not the API request or confidence.

For [Cloudflare Workers AI](https://developers.cloudflare.com/workers-ai/), choose the Cloudflare preset, enter your Account ID and API token, and select one of the supported models:

- `@cf/cloudflare/clef-flash` (vision-capable)
- `@cf/cloudflare/clef` (vision-capable)
- `typesafe/jev`

For local [Ollama](https://ollama.com), the default URL is `http://localhost:11434`. Choose None if your server does not require credentials. The app loads decision-capable models from `GET /api/tags` (automatically detecting vision capabilities) and uses `GET /v1/models` for Jev and Bearer connections. Decision requests default to `/v1/systemone`.

Install a decision-capable model on your Ollama server, such as:

- `nimble`
- `tev1`
- `clef` (vision-capable)
- `clef-flash` (vision-capable)
- `laya`

![A decision request and its response in Clef Airy Decisions API Tester.](docs/run.jpg)

## Credentials and local data

Settings and bookmarks stay in a local SQLite database. Bookmarks do not store attached images. The desktop app encrypts saved API passwords and Bearer keys through Electron's secure storage, using macOS Keychain or Windows data protection. It saves the encrypted value in the database. If secure storage is unavailable, saving a new credential fails.

The app sends credentials to your configured API to authenticate model lookups and requests. It sends request content there too. Use HTTPS for remote APIs.

On first launch, each desktop installation creates a random Laravel application key in its local app-data folder. Saved API credentials use the operating system's encryption separately. The repo includes the code that generates the Laravel key. The key itself stays local: production builds remove `APP_KEY` and exclude `.key` files.

macOS may ask for Keychain access when you save or use a credential. The app converts older Laravel-encrypted credentials to OS-backed encryption if it can read them. If it cannot, re-enter them in Configuration.

## Technical information

Built with [Laravel](https://laravel.com) and [NativePHP for Desktop](https://nativephp.com/docs/desktop/getting-started/introduction), with Blade, Alpine.js, Tailwind CSS, and Vite for the interface. Desktop data is stored in SQLite.

### Development requirements

- PHP 8.3 or newer
- Composer
- Node.js 22 or newer
- SQLite

### Set up from source

```bash
composer run setup
```

This installs dependencies, creates `.env` if needed, generates a development application key, runs migrations, and builds the frontend.

Keep `.env` private. Browser development mode encrypts saved credentials with the Laravel key in `.env`. The desktop app uses OS-backed encryption.

### Run from source

In the browser:

```bash
composer run dev
```

For desktop development with Vite:

```bash
composer native:dev
```

### Tests

```bash
composer test
```

## Build desktop installers

### macOS

Quit any copy running from `nativephp/electron/dist` before rebuilding. The build replaces that bundle, including files used by its PHP server.

For an Apple Silicon build:

```bash
php artisan native:build mac arm64 --no-interaction
```

For Intel Macs:

```bash
php artisan native:build mac x64 --no-interaction
```

Without a signing identity, Mac builds use ad-hoc signing and are not notarized. If macOS blocks your local build, run:

```bash
xattr -dr com.apple.quarantine 'nativephp/electron/dist/mac-arm64/Clef Airy Decisions API Tester.app'
```

For an Intel build, use `dist/mac/` instead of `dist/mac-arm64/`. If your build has a different app name, use that name in the path.

Increment the app version in `config/nativephp.php`, or set `NATIVEPHP_APP_VERSION` in your local `.env`. NativePHP checks this version string on boot to run database migrations on installed copies.

Production builds remove `APP_ENV` and `APP_DEBUG` from the bundled environment. The app then runs in production with debug pages off.

### Signed releases

For a signed and notarized Mac build, configure a Developer ID Application certificate. Set `NATIVEPHP_APPLE_ID`, `NATIVEPHP_APPLE_ID_PASS` (an app-specific password), and `NATIVEPHP_APPLE_TEAM_ID` in your local, ignored `.env`. See the [NativePHP build guide](https://nativephp.com/docs/desktop/2/publishing/building) for setup details.

```bash
NATIVEPHP_RELEASE=true php artisan native:build mac arm64 --no-interaction
```

This enables release mode, which requires signing and notarization credentials and fails if notarization fails. Installers are written to `nativephp/electron/dist`. Keep the bundle identifier the same between releases.

### Windows

After source setup, build the Windows x64 installer:

```bash
php artisan native:build win x64 --no-interaction
```

The installer is written to `nativephp/electron/dist` as `*-setup.exe`. Windows builds use `public/icon.ico`. The configured prebuild hook uses `cp`, so builds on Windows need a shell that provides it, such as Git Bash.

## Upgrading NativePHP

The customized Electron source lives in `nativephp/electron`. It includes signing configuration and the code that generates each installation's Laravel key. It does not include signing certificates, private keys, or local credentials.

After updating `nativephp/desktop`, manually compare `vendor/nativephp/desktop/resources/electron` with `nativephp/electron` and merge the relevant upstream changes. Keep the app's signing configuration, per-installation Laravel key, runtime cache paths, plugin build scripts, and Electron 42 dependency versions. Do not overwrite the customized shell with `native:install --publish`.

```bash
cd nativephp/electron
npm install
npm run plugin:test
```

Commit changes to the Electron source and its npm lockfile together, then build and launch the app to verify the upgrade. Normal builds use the committed Electron source directly.

## License

Clef Airy Decisions API Tester is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
