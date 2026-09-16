<?php

namespace App\UI\Screens\Admin\Concerns;

use App\UI\Screens\Admin\TableModels\UserTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait BuildsUsersSection
{
    protected const USERS_I18N_PREFIX = 'screen.admin.users_manager.';

    protected function buildUsersCrudContainer(): Container
    {
        $usersCrudContainer = UI::container('users_crud_container')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->rounded(0)
            ->height(Size::px(340))
            ->plain();

        $toolbar = UI::container('users_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(10));

        $search = UI::input('search_users')
            ->placeholder(t(self::USERS_I18N_PREFIX . 'search_placeholder'))
            ->width(Size::px(300))
            ->autocomplete('off')
            ->onInput('search_users', [])
            ->debounce(500);

        $addBtn = UI::button('add_user_btn')
            ->label(t(self::USERS_I18N_PREFIX . 'add_user'))
            ->style('secondary')
            ->action('add_user_clicked')
            ->icon('plus');

        $toolbar->add($search)->add($addBtn);

        $usersTable = UI::table('users_table');
        $usersTable->pagination(7);
        $usersTable->sortedBy('name');
        $usersTable->dataModel(UserTableModel::class);
        $usersTable->selectionMode(SelectionMode::SINGLE);
        $usersTable->bodyOverflowX('hidden');
        $usersTable->bodyOverflowY('auto');
        $usersTable->minHeight(Size::px(460));
        $usersTable->bodyMinHeight('340px');
        $usersTable->align('center');

        $usersCrudContainer
            ->add($toolbar)
            ->add($usersTable);

        return $usersCrudContainer;
    }
}
