<?php

namespace App\Providers;

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
            ->route('configuration.edit')
            ->width(1040)
            ->height(720)
            ->minWidth(720)
            ->minHeight(520)
            ->rememberState()
            ->suppressNewWindows()
            ->preventLeaveDomain()
            ->backgroundColor('#e2e6ee');
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
