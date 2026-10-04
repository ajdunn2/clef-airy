<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use SplFileInfo;

class ElectronCustomizations
{
    /**
     * Directories created by local installs and builds. Republish leaves them alone.
     *
     * @var list<string>
     */
    public const SKIPPED_DIRECTORIES = ['node_modules', 'out', 'dist', 'coverage'];

    /**
     * Build a fresh Electron project from the installed NativePHP package, then reapply this app's patch.
     */
    public function stage(string $destination): void
    {
        if (is_dir($destination)) {
            throw new RuntimeException("Staging directory already exists: {$destination}");
        }

        $stock = $this->stockPath();

        if (! is_dir($stock)) {
            throw new RuntimeException('Install nativephp/desktop before republishing the Electron project.');
        }

        File::ensureDirectoryExists($destination);
        $this->copyTree($stock, $destination);
        $this->applyPatch($destination);
        $this->applyPackageMetadata($destination);
        $this->copyIcons($destination);
    }

    /**
     * Replace the published Electron project with a staged copy of the installed shell.
     */
    public function republish(bool $installDependencies = false): void
    {
        $staging = sys_get_temp_dir().'/clef-airy-electron-'.bin2hex(random_bytes(4));

        try {
            $this->stage($staging);
            $this->syncOntoPublished($staging);
        } finally {
            File::deleteDirectory($staging);
        }

        if ($installDependencies) {
            $this->installDependencies();
        }
    }

    public function stockPath(): string
    {
        return base_path('vendor/nativephp/desktop/resources/electron');
    }

    public function publishedPath(): string
    {
        return base_path('nativephp/electron');
    }

    public function patchPath(): string
    {
        return base_path('nativephp/electron-customizations.patch');
    }

    private function syncOntoPublished(string $staging): void
    {
        $published = $this->publishedPath();

        File::ensureDirectoryExists($published);
        $this->copyTree($staging, $published);
        $this->deleteExtras($published, $staging);
    }

    private function applyPatch(string $electronDirectory): void
    {
        $patch = $this->patchPath();

        if (! is_file($patch)) {
            throw new RuntimeException('The Electron customization patch is missing.');
        }

        $result = Process::path($electronDirectory)->run([
            'git', 'apply', '-p1', '--whitespace=nowarn', $patch,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'The Electron customization patch did not apply.');
        }
    }

    private function applyPackageMetadata(string $electronDirectory): void
    {
        $path = $electronDirectory.'/package.json';
        $package = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($package) || ! is_array($package['scripts'] ?? null)) {
            throw new RuntimeException('The Electron package.json could not be read.');
        }

        $scripts = $package['scripts'];

        if (! isset($scripts['dev'], $scripts['build']) || ! is_string($scripts['dev']) || ! is_string($scripts['build'])) {
            throw new RuntimeException('The NativePHP Electron package.json no longer has dev and build scripts.');
        }

        $scripts['dev'] = $this->withPluginBuild($scripts['dev']);
        $scripts['build'] = $this->withPluginBuild($scripts['build']);
        unset($scripts['build:mac-release']);

        // NativePHP rewrites the npm name from the display name during a build.
        // The published package keeps the shorter name used by this repository.
        $package['name'] = 'clef-airy';
        $package['version'] = config('nativephp.version');
        $package['description'] = config('nativephp.description');
        $package['author'] = config('nativephp.author');
        $package['homepage'] = config('nativephp.website');
        $package['scripts'] = [
            'build:mac-release' => 'cross-env NATIVEPHP_RELEASE=true npm run build:mac',
            ...$scripts,
        ];

        File::put($path, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function withPluginBuild(string $command): string
    {
        if (str_contains($command, 'plugin:build')) {
            return $command;
        }

        return 'npm run plugin:build && '.$command;
    }

    private function copyIcons(string $electronDirectory): void
    {
        foreach (['icon.png', 'icon.icns', 'icon.ico'] as $icon) {
            $source = public_path($icon);

            if (! is_file($source)) {
                throw new RuntimeException("Missing public/{$icon}.");
            }

            File::ensureDirectoryExists($electronDirectory.'/build');
            File::copy($source, $electronDirectory.'/build/'.$icon);
        }
    }

    private function installDependencies(): void
    {
        $result = Process::path($this->publishedPath())
            ->timeout(600)
            ->run(['npm', 'install']);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'npm install failed in the published Electron project.');
        }
    }

    private function copyTree(string $source, string $destination): void
    {
        foreach ($this->entries($source) as $relative => $item) {
            $target = $destination.'/'.$relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($item->getPathname(), $target);
        }
    }

    private function deleteExtras(string $published, string $staging): void
    {
        $entries = [];

        foreach ($this->entries($published, childFirst: true) as $relative => $item) {
            $entries[] = [$relative, $item->getPathname(), $item->isDir()];
        }

        foreach ($entries as [$relative, $path, $isDirectory]) {
            $staged = $staging.'/'.$relative;

            if (! $isDirectory && ! is_file($staged)) {
                File::delete($path);
            }

            if ($isDirectory && ! is_dir($staged)) {
                @rmdir($path);
            }
        }
    }

    /**
     * @return \Generator<string, SplFileInfo>
     */
    private function entries(string $root, bool $childFirst = false): \Generator
    {
        $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $filtered = new \RecursiveCallbackFilterIterator($directory, function (SplFileInfo $current): bool {
            return ! ($current->isDir() && in_array($current->getFilename(), self::SKIPPED_DIRECTORIES, true));
        });
        $mode = $childFirst ? \RecursiveIteratorIterator::CHILD_FIRST : \RecursiveIteratorIterator::SELF_FIRST;
        $iterator = new \RecursiveIteratorIterator($filtered, $mode);

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            yield str_replace('\\', '/', $iterator->getSubPathname()) => $item;
        }
    }
}
