<?php

namespace App\Providers;

use App\Models\SystemOneSetting;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->title('Clef Airy Decisions')
            ->route(static::initialRoute())
            ->width(1040)
            ->height(720)
            ->minWidth(720)
            ->minHeight(520)
            ->rememberState()
            ->suppressNewWindows()
            ->preventLeaveDomain()
            ->backgroundColor('#e2e6ee');
    }

    public static function initialRoute(): string
    {
        return SystemOneSetting::current() === null ? 'configuration.edit' : 'run.create';
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
