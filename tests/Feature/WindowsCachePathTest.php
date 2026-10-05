<?php

namespace Tests\Feature;

use Tests\TestCase;

class WindowsCachePathTest extends TestCase
{
    private string $packageCachePath = 'C:\\Users\\andrew\\AppData\\Roaming\\clef-airy-decisions\\bootstrap\\cache\\packages.php';

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_PACKAGES_CACHE'] = $this->packageCachePath;
        $_SERVER['APP_PACKAGES_CACHE'] = $this->packageCachePath;
        putenv('APP_PACKAGES_CACHE='.$this->packageCachePath);
    }

    protected function tearDown(): void
    {
        putenv('APP_PACKAGES_CACHE');
        unset($_ENV['APP_PACKAGES_CACHE'], $_SERVER['APP_PACKAGES_CACHE']);

        parent::tearDown();
    }

    public function test_windows_package_cache_path_stays_in_the_user_data_folder(): void
    {
        $resolved = $this->app->getCachedPackagesPath();

        $this->assertSame($this->packageCachePath, $resolved);
    }
}
