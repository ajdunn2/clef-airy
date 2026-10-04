<?php

namespace Tests\Feature;

use App\Models\SystemOneSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\System;
use Tests\TestCase;

class DesktopSecurityTest extends TestCase
{
    public function test_remote_http_credentials_can_be_saved(): void
    {
        $this->put('/configuration', [
            'api_url' => 'http://api.example.test',
            'username' => 'test-user',
            'password' => 'test-password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('test-password', SystemOneSetting::current()->password);
    }

    public function test_saved_credentials_can_be_used_with_remote_http(): void
    {
        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'password' => 'test-password',
        ])->assertSessionHasNoErrors();

        $this->put('/configuration', [
            'api_url' => 'http://api.example.test',
            'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('http://api.example.test', SystemOneSetting::current()->base_url);
        $this->assertSame('test-password', SystemOneSetting::current()->password);
    }

    public function test_local_http_credentials_can_be_saved(): void
    {
        $this->put('/configuration', [
            'api_url' => 'http://localhost:11434',
            'password' => 'test-password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('test-password', SystemOneSetting::current()->password);
    }

    public function test_existing_remote_http_credentials_are_sent(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://api.example.test/*' => Http::response([])]);
        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => 'test-password']);
        DB::table('system_one_settings')->update(['base_url' => 'http://api.example.test']);

        $this->post('/run', ['method' => 'GET', 'path' => '/events'])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request->url() === 'http://api.example.test/events'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(':test-password')));
    }

    public function test_native_passwords_are_encrypted_by_the_operating_system(): void
    {
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('canEncrypt')->once()->andReturn(true);
        System::shouldReceive('encrypt')->once()->with('test-password')->andReturn('os-ciphertext');
        System::shouldReceive('decrypt')->once()->with('os-ciphertext')->andReturn('test-password');

        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => 'test-password'])
            ->assertSessionHasNoErrors();

        $setting = SystemOneSetting::current();
        $this->assertSame('native:v1:os-ciphertext', $setting->getRawOriginal('password'));
        $this->assertSame('test-password', $setting->password);
        $this->assertArrayNotHasKey('password', $setting->toArray());
    }

    public function test_local_encrypted_passwords_are_migrated_to_secure_storage(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => 'test-password']);
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('canEncrypt')->once()->andReturn(true);
        System::shouldReceive('encrypt')->once()->with('test-password')->andReturn('os-ciphertext');

        SystemOneSetting::current();

        $this->assertDatabaseHas('system_one_settings', ['password' => 'native:v1:os-ciphertext']);
    }

    public function test_showing_configuration_does_not_read_keychain_passwords(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test']);
        DB::table('system_one_settings')->update(['password' => 'native:v1:os-ciphertext']);
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('decrypt')->never();
        System::shouldReceive('encrypt')->never();

        $this->get('/')->assertOk()->assertSee('A password is saved. Leave this blank to keep it.');
    }

    public function test_native_configuration_without_passwords_does_not_access_secure_storage(): void
    {
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('canEncrypt')->never();
        System::shouldReceive('encrypt')->never();
        System::shouldReceive('decrypt')->never();

        $this->put('/configuration', ['api_url' => 'http://localhost:11434'])->assertSessionHasNoErrors();
        $this->get('/')->assertOk();
    }

    public function test_unreadable_legacy_passwords_are_preserved_and_request_reentry(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test']);
        DB::table('system_one_settings')->update(['password' => 'unreadable-legacy-ciphertext']);

        $this->get('/')->assertOk()->assertSee('Your saved password cannot be decrypted. Re-enter it, or remove it if your API does not require a password.');
        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_one_settings', ['password' => 'unreadable-legacy-ciphertext']);
    }

    public function test_unreadable_passwords_cannot_send_an_unauthenticated_request(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.example.test/*' => Http::response([])]);
        $this->put('/configuration', ['api_url' => 'https://api.example.test']);
        DB::table('system_one_settings')->update(['password' => 'unreadable-legacy-ciphertext']);

        $this->post('/run', ['method' => 'GET', 'path' => '/events'])
            ->assertSessionHasErrors(['path' => 'Re-enter your API password in Configuration before sending a request.']);

        Http::assertNothingSent();
    }

    public function test_unreadable_password_can_be_explicitly_removed(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test']);
        DB::table('system_one_settings')->update(['password' => 'unreadable-legacy-ciphertext']);

        $this->put('/configuration', ['api_url' => 'http://localhost:11434', 'remove_password' => '1'])
            ->assertSessionHasNoErrors();

        $setting = SystemOneSetting::current();
        $this->assertSame('', $setting->password);
        $this->assertFalse($setting->passwordNeedsReset());
    }

    public function test_new_password_takes_precedence_over_removal(): void
    {
        $this->put('/configuration', [
            'api_url' => 'https://api.example.test',
            'password' => 'replacement-password',
            'remove_password' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('replacement-password', SystemOneSetting::current()->password);
    }

    public function test_an_unreadable_keychain_password_is_preserved(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test']);
        DB::table('system_one_settings')->update(['password' => 'native:v1:os-ciphertext']);
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('decrypt')->once()->with('os-ciphertext')->andReturn(null);

        $this->assertNull(SystemOneSetting::current()->password);

        $this->assertDatabaseHas('system_one_settings', ['password' => 'native:v1:os-ciphertext']);
    }

    public function test_a_readable_legacy_password_stays_when_secure_storage_is_unavailable(): void
    {
        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => 'test-password']);
        $legacy = DB::table('system_one_settings')->value('password');
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('canEncrypt')->twice()->andReturn(false);

        $setting = SystemOneSetting::current();

        $this->assertSame('test-password', $setting->password);
        $this->assertSame($legacy, $setting->getRawOriginal('password'));
        $this->get('/')->assertOk();
    }

    public function test_unavailable_secure_storage_does_not_save_a_password(): void
    {
        config(['nativephp-internal.running' => true]);
        System::shouldReceive('canEncrypt')->once()->andReturn(false);

        $this->put('/configuration', ['api_url' => 'https://api.example.test', 'password' => 'test-password'])
            ->assertSessionHasErrors(['password']);

        $this->assertDatabaseCount('system_one_settings', 0);
    }
}
