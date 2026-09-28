<?php

namespace App\UI\Screens\Testing;

use App\UI\Screens\Admin\AdminMenu;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class CustomAdminFramedScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $menuScreen = AdminMenu::class;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::label('admin_screen_title')->text('Admin Screen Content'));
    }
}
