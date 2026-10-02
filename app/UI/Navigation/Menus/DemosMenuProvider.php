<?php

namespace App\UI\Navigation\Menus;

use App\UI\Screens\Demo\ButtonDemo;
use App\UI\Screens\Demo\CarouselDemo;
use App\UI\Screens\Demo\CheckboxDemo;
use App\UI\Screens\Demo\FormDemo;
use App\UI\Screens\Demo\InputDemo;
use App\UI\Screens\Demo\ModalDemo;
use App\UI\Screens\Demo\SelectDemo;
use App\UI\Screens\Demo\SplitDemo;
use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Demo\TabsDemo;
use App\UI\Screens\Demo\TextareaDemo;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\Navigation\MenuBuilder;

class DemosMenuProvider implements MenuProviderInterface
{
    public function build(MenuBuilder $menu): void
    {
        $menu->submenu(t('screen.menu.items.demos'), function (MenuBuilder $submenu) {
            $submenu->screenShow(ButtonDemo::class);
            $submenu->screenShow(TableDemo::class);
            $submenu->screenShow(ModalDemo::class);
            $submenu->action(t('screen.menu.demos.abort_error'), 'show_error_info', [], '❌');
            $submenu->screenShow(FormDemo::class);
            $submenu->screenShow(InputDemo::class);
            $submenu->screenShow(SelectDemo::class);
            $submenu->screenShow(CheckboxDemo::class);
            $submenu->screenShow(CarouselDemo::class);
            $submenu->screenShow(TextareaDemo::class);
            $submenu->screenShow(SplitDemo::class);
            $submenu->screenShow(TabsDemo::class);
        }, '🎮');
    }
}
