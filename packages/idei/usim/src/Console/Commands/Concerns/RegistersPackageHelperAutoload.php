<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait RegistersPackageHelperAutoload
{
    protected function registerPackageHelpersAutoload(): void
    {
        $this->newLine();
        $this->info('Registering package helpers in composer autoload...');

        $composerPath = \base_path('composer.json');

        if (!$this->files->exists($composerPath)) {
            $this->line('  <fg=yellow>!</> composer.json not found, skipping helper registration');
            return;
        }

        $composerRaw = $this->files->get($composerPath);
        $composerConfig = json_decode($composerRaw, true);

        if (!\is_array($composerConfig)) {
            $this->line('  <fg=yellow>!</> composer.json is not valid JSON, skipping helper registration');
            return;
        }

        $composerConfig['autoload'] ??= [];
        $composerConfig['autoload']['files'] ??= [];

        if (!\is_array($composerConfig['autoload']['files'])) {
            $composerConfig['autoload']['files'] = [];
        }

        $helperAutoloadPath = 'vendor/idei/usim/src/Support/helpers.php';

        if (\in_array($helperAutoloadPath, $composerConfig['autoload']['files'], true)) {
            $this->line('  <fg=blue>→</> Package helper already registered in composer autoload.files');
            return;
        }

        $composerConfig['autoload']['files'][] = $helperAutoloadPath;

        $encoded = json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->line('  <fg=yellow>!</> Failed to encode composer.json, skipping helper registration');
            return;
        }

        $this->files->put($composerPath, $encoded . PHP_EOL);
        $this->line('  <fg=green>✓</> Package helper registered in composer autoload.files');
    }
}
