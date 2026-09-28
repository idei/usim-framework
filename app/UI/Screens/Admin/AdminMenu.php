<?php

namespace App\UI\Screens\Admin;

use App\UI\Navigation\Menus\AdminMenuProvider;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class AdminMenu extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static bool $hasMenu = false;
    public static ?string $layout = null;

    protected MenuDropdown $admin_menu;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(0))
            ->marginBottom(Spacing::px(0));

        $builder = MenuBuilder::make('admin_menu')
            ->trigger('⚡ Admin')
            ->position('bottom-left')
            ->width(Size::px(220))
            ->provider(AdminMenuProvider::class);

        $this->admin_menu = $builder->render('admin_menu');
        $container->add($this->admin_menu);
    }
}
