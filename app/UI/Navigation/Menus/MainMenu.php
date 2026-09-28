<?php

namespace App\UI\Navigation\Menus;

use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\Navigation\MenuBuilder;

class MainMenu implements MenuProviderInterface
{
    public function build(MenuBuilder $menu): void
    {
        $menu->link(t('screen.menu.items.home'), '/', '🏠');
        $menu->screen(UsersManager::class);
        $menu->screen(TranslateManager::class);

        $menu->separator();
        $menu->provider(DemosMenuProvider::class);

        $menu->separator();
        $menu->action(t('screen.menu.items.about'), 'show_about_info', [], 'ℹ️');
    }
}
