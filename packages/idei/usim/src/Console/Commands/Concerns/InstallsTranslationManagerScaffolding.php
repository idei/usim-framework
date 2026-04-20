<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait InstallsTranslationManagerScaffolding
{
    protected function installTranslationManagerScaffolding(): void
    {
        $this->newLine();
        $this->info('Installing Translation Manager scaffolding...');

        $this->installScreen('Admin/TranslateManager.php.stub', 'TranslateManager.php', 'Admin');
        $this->installComponent('Modals/EditTranslationDialog.php.stub', 'EditTranslationDialog.php', 'Modals');
        $this->installComponent('DataTable/TranslationKeysTableModel.php.stub', 'TranslationKeysTableModel.php', 'DataTable');
    }
}
