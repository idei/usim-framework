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
        $menu->screenShow(
            UsersManager::class,
            when: UsersManager::checkAccess()['allowed'] == true
        );
        $menu->screenShow(
            TranslateManager::class,
            when: TranslateManager::checkAccess()['allowed'] == true
        );

        $menu->separator();
        $menu->provider(DemosMenuProvider::class);

        $menu->separator();
        $menu->action(t('screen.menu.items.about'), 'show_about_info', [], 'ℹ️');
    }
}
