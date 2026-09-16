<?php

namespace App\UI\Screens\Admin\Concerns;

use App\UI\Screens\Admin\TableModels\PermissionTableModel;
use App\UI\Screens\Admin\TableModels\RoleTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait BuildsRolesSection
{
    protected function buildRolesContainer(): Container
    {
        $rolesContainer = UI::container('roles_container')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->rounded(0)
            ->plain();

        $rolesSplit = UI::split('roles_split')
            ->horizontal()
            ->splitSize('65%')
            ->splitterSize('8px')
            ->draggable(true)
            ->minFirstSize('350px')
            ->minSecondSize('450px')
            ->padding(Spacing::px(4))
            ->height(Size::px(530))
            ->width(Size::full())
            ->plain();

        $rolesLeftPanel = UI::container('roles_left_panel')
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(4))
            ->plain();

        $rolesRightPanel = UI::container('roles_right_panel')
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(4))
            ->plain();

        $rolesTable = UI::table('roles_table');
        $rolesTable->pagination(0);
        $rolesTable->height(Size::px(500));
        $rolesTable->rounded(0);
        $rolesTable->sortedBy('name');
        $rolesTable->dataModel(RoleTableModel::class);
        $rolesTable->bodyOverflowX('hidden');
        $rolesTable->bodyOverflowY('auto');
        $rolesTable->selectionMode(SelectionMode::SINGLE);

        $rolesLeftPanel->add($rolesTable);

        $permissionsTable = UI::table('permissions_table');
        $permissionsTable->pagination(0);
        $permissionsTable->height(Size::px(500));
        $permissionsTable->bodyHeight(Size::px(450));
        $permissionsTable->rounded(0);
        $permissionsTable->sortedBy('name');
        $permissionsTable->dataModel(PermissionTableModel::class);
        $permissionsTable->bodyOverflowX('hidden');
        $permissionsTable->bodyOverflowY('auto');
        $permissionsTable->selectionMode(SelectionMode::MULTIPLE);

        $rolesRightPanel->add($permissionsTable);

        $rolesSplit
            ->addFirst($rolesLeftPanel)
            ->addSecond($rolesRightPanel);

        $rolesContainer->add($rolesSplit);

        return $rolesContainer;
    }
}
