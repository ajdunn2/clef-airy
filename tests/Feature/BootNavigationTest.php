<?php

namespace Tests\Feature;

use App\Providers\NativeAppServiceProvider;
use Tests\TestCase;

class BootNavigationTest extends TestCase
{
    public function test_the_app_opens_on_configuration_until_the_api_url_is_saved(): void
    {
        $this->assertSame('configuration.edit', NativeAppServiceProvider::initialRoute());

        $this->put('/configuration', ['api_url' => 'http://api.example.test']);

        $this->assertSame('run.create', NativeAppServiceProvider::initialRoute());
    }
}
