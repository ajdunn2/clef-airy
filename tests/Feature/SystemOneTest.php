<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemOneTest extends TestCase
{
    public function test_home_page_shows_configuration_and_run(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Configuration')
            ->assertSee('Compose Request')
            ->assertSee('API URL')
            ->assertSee('Model')
            ->assertSee('Enter credentials only if your API requires basic authentication.')
            ->assertSee('Password');
    }
}
