<?php

namespace App\Console\Commands;

use App\Services\ElectronCustomizations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('electron:republish {--install : Install npm dependencies in the published Electron project}')]
#[Description('Replace the published Electron shell with the installed NativePHP version and reapply this app\'s customizations')]
class RepublishElectronCommand extends Command
{
    public function handle(ElectronCustomizations $customizations): int
    {
        $customizations->republish((bool) $this->option('install'));

        $this->components->info('Reapplied the Electron customizations.');

        if (! $this->option('install')) {
            $this->components->info('Pass --install when a NativePHP upgrade changes the Electron npm dependencies.');
        }

        return self::SUCCESS;
    }
}
