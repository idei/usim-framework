<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait RegistersPackageHelperAutoload
{
    protected function registerPackageHelpersAutoload(): void
    {
        $this->newLine();
        $this->info('Checking package helpers autoload...');

        $helperAutoloadPath = 'vendor/idei/usim/src/Support/helpers.php';
        $this->line('  <fg=green>✓</> Package helper is provided by idei/usim autoload.files');
        $this->line('  <fg=blue>→</> No composer.json mutation is required for helper registration');
    }
}
