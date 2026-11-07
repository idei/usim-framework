<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Console\Commands\Support\BaseScaffoldCommand;

class UsimRequestScaffoldCommand extends BaseScaffoldCommand
{
    protected $signature = 'usim:request
        {name : The name of the UIRequest to scaffold}
        {--force : Overwrite existing files if they exist}
        {--i18n : Generate corresponding language files in lang/ compatible with t()}';

    protected $description = 'Scaffold a new UIRequest class in the predefined requests folder.';

    public function handle(): int
    {
        $this->info('Scaffolding a new UIRequest...');
        $name = (string) $this->argument('name');
        $force = (bool) $this->option('force');
        $i18n = (bool) $this->option('i18n');

        $stubRelativePath = 'scaffolding/request.php.stub';
        if ($i18n) {
            $i18nStubCandidate = 'scaffolding/request_i18n.php.stub';
            if ($this->filesystem->exists($this->stubsPath($i18nStubCandidate))) {
                $stubRelativePath = $i18nStubCandidate;
            }
        }

        $target = $this->resolveTarget(
            $name,
            $this->config->requestsNamespace,
            $this->config->requestsPath,
            forceSuffix: 'Request'
        );

        $langTarget = $this->resolveLangTarget('requests', $name, forceSuffix: 'Request');

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

        $this->info("UIRequest '{$target['className']}' has been scaffolded at '{$target['filePath']}'.");

        if ($i18n) {
            $createdLangFiles = $this->generateLangFiles(
                element: 'request',
                relativeLangPath: $langTarget['relativePath'],
                replacements: $replacements,
                force: $force
            );

            foreach ($createdLangFiles as $langFile) {
                $this->info("Translation created: '{$langFile}' (Key: '{$langTarget['langKey']}')");
            }
        }

        $this->info("You can type-hint it in your Screen handler: onSave({$target['className']} \$request)");

        return 0;
    }
}
