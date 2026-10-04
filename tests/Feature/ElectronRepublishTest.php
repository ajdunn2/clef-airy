<?php

namespace Tests\Feature;

use App\Services\ElectronCustomizations;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

class ElectronRepublishTest extends TestCase
{
    public function test_staged_electron_shell_matches_the_published_project(): void
    {
        $staging = sys_get_temp_dir().'/clef-airy-electron-test-'.bin2hex(random_bytes(4));
        $customizations = new ElectronCustomizations;

        try {
            $customizations->stage($staging);

            $published = $this->relativeFiles($customizations->publishedPath());
            $staged = $this->relativeFiles($staging);

            $this->assertSame($published, $staged);

            foreach ($published as $relative) {
                $this->assertSame(
                    file_get_contents($customizations->publishedPath().'/'.$relative),
                    file_get_contents($staging.'/'.$relative),
                    $relative,
                );
            }
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * @return list<string>
     */
    private function relativeFiles(string $root): array
    {
        $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $filtered = new \RecursiveCallbackFilterIterator($directory, function (SplFileInfo $current): bool {
            return ! ($current->isDir() && in_array($current->getFilename(), ElectronCustomizations::SKIPPED_DIRECTORIES, true));
        });
        $iterator = new \RecursiveIteratorIterator($filtered);
        $files = [];

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $files[] = str_replace('\\', '/', $iterator->getSubPathname());
        }

        sort($files);

        return $files;
    }
}
