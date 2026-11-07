<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Console\Commands\Support\BaseScaffoldCommand;

class UsimScreenScaffoldCommand extends BaseScaffoldCommand
{
    protected $signature = 'usim:screen
        {name : The name of the screen to scaffold}
        {--type=basic : The template to use}
        {--force : Overwrite existing files if they exist}
        {--request : Also scaffold a matching UIRequest}
        {--i18n : Generate corresponding language files in lang/ compatible with t()}';

    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['usim:scaf'];

    protected $description = 'Scaffold a new screen class with the specified name and template.';

    public function handle(): int
    {
        $this->info('Scaffolding a new screen...');
        $name = (string) $this->argument('name');
        $type = (string) $this->option('type');
        $force = (bool) $this->option('force');
        $i18n = (bool) $this->option('i18n');

        $stubRelativePath = "scaffolding/{$type}.php.stub";
        if ($i18n) {
            $i18nStubCandidate = "scaffolding/{$type}_i18n.php.stub";
            if ($this->filesystem->exists($this->stubsPath($i18nStubCandidate))) {
                $stubRelativePath = $i18nStubCandidate;
            }
        }

        $target = $this->resolveTarget(
            $name,
            $this->config->screensNamespace,
            $this->config->screensPath
        );

        $langTarget = $this->resolveLangTarget('screens', $name);

        $replacements = [
            'name' => $target['className'],
            'namespace' => $target['fullNamespace'],
            'langKey' => $langTarget['langKey'],
            'humanName' => $langTarget['humanName'],
        ];

        $success = $this->generateFromStub(
            $stubRelativePath,
            $target['filePath'],
            $replacements,
            $force
        );

        if (! $success) {
            return 1;
        }

        $this->info("Screen '{$target['className']}' has been scaffolded at '{$target['filePath']}'.");

        if ($i18n) {
            $createdLangFiles = $this->generateLangFiles(
                element: 'screen',
                relativeLangPath: $langTarget['relativePath'],
                replacements: $replacements,
                force: $force
            );

            foreach ($createdLangFiles as $langFile) {
                $this->info("Translation created: '{$langFile}' (Key: '{$langTarget['langKey']}')");
            }
        }

        $this->info("Don't forget to make php artisan usim:discover to register the new screen.");

        if ($this->option('request')) {
            $normalized = str_replace('\\', '/', trim($name));
            $parts = array_values(array_filter(explode('/', $normalized)));
            $rawClass = array_pop($parts) ?? 'Screen';
            $requestName = ($parts !== [] ? implode('/', $parts).'/' : '').$rawClass.'Request';

            $this->call('usim:request', [
                'name' => $requestName,
                '--force' => $force,
                '--i18n' => $i18n,
            ]);
        }

        return 0;
    }
}
