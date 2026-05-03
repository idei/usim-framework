<?php

namespace App\UI\Screens\Demo;

use App\UI\Components\DataTable\UsersTableModel;
use App\UI\Screens\Demo\Support\TableDemoService;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Table;
use Idei\Usim\Screen;
use Idei\Usim\UI;

/**
 * Table Demo Service
 *
 * Demonstrates table functionality with:
 * - AbstractDataTableModel for data management
 * - Pagination handled by the model
 * - Edit and Remove action buttons
 * - Column width constraints
 *
 * Version: 2.0 (with DataTableModel abstraction)
 */
class TableDemo extends Screen
{
    protected Table $users_table;
    private ?TableDemoService $tableDemoService = null;

    private function tableDemoService(): TableDemoService
    {
        if ($this->tableDemoService === null) {
            $this->tableDemoService = app(TableDemoService::class);
        }

        return $this->tableDemoService;
    }

    /**
     * Build the table demo UI
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->plain()
            ->add(
                UI::label()
                    ->text(t('screen.demo.table_demo.title'))
                    ->style('h2')
            );

        $table = UI::table('users_table')
            ->title(t('screen.demo.table_demo.users_table_title'))
            ->pagination(10)
            ->dataModel(UsersTableModel::class)
            ->align('center')
            ->width('600px')
            ->rowMinHeight(45);

        $container->add($table);
    }

    public function onEditUser(array $params): void
    {
        $id = (int) ($params['user_id'] ?? 0);
        $user = $this->tableDemoService()->find($id);
        if (!$user) {
            return;
        }
        $name = $user['name'] ?? 'Unknown';
        $this->toast("Editando usuario: {$name} (ID: {$id})");
        $updateData = ['name' => "$name (E)"];
        $this->users_table->getModel()->updateRow($id, $updateData);
    }

    public function onRemoveUser(array $params): void
    {
        $id = (int) ($params['user_id'] ?? 0);
        $user = $this->tableDemoService()->find($id);
        if (!$user) {
            return;
        }

        /** @var UsersTableModel|null $model */
        $model = $this->users_table->getModel();
        if ($model instanceof UsersTableModel && $model->deleteUser($id)) {
            $this->users_table->refresh();
        }
    }

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->users_table->page($page);
    }

}
