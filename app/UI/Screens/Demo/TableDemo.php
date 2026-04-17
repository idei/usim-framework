<?php

namespace App\UI\Screens\Demo;

use App\Models\User;
use Idei\Usim\UIBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Components\Table;
use App\UI\Components\DataTable\UsersTableModel;

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

    /**
     * Build the table demo UI
     */
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container->title(t('screen.demo.table_demo.title'));

        $table = UIBuilder::table('users_table')
            ->title(t('screen.demo.table_demo.users_table_title'))
            ->pagination(10)
            ->dataModel(UsersTableModel::class)
            ->align('center')
            ->rowMinHeight(40);

        $container->add($table);
    }

    public function onEditUser(array $params): void
    {
        $id = $params['user_id'] ?? null;
        $user = User::find($id);
        $updateData = ['name' => "{$user->name} (E)"];
        $this->users_table->getModel()->updateRow($id, $updateData);
    }

    public function onRemoveUser(array $params): void
    {
        $id = $params['user_id'] ?? null;
    }

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->users_table->page($page);
    }

}
