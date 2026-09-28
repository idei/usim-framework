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
            $submenu->screen(ButtonDemo::class, t('screen.menu.demos.button_demo'), '🖲️');
            $submenu->screen(TableDemo::class, t('screen.menu.demos.table_demo'), '📊');
            $submenu->screen(ModalDemo::class, t('screen.menu.demos.modal_demo'), '🪟');
            $submenu->action(t('screen.menu.demos.abort_error'), 'show_error_info', [], '❌');
            $submenu->screen(FormDemo::class, t('screen.menu.demos.form_demo'), '📝');
            $submenu->screen(InputDemo::class, t('screen.menu.demos.input_demo'), '⌨️');
            $submenu->screen(SelectDemo::class, t('screen.menu.demos.select_demo'), '📋');
            $submenu->screen(CheckboxDemo::class, t('screen.menu.demos.checkbox_demo'), '☑️');
            $submenu->screen(CarouselDemo::class, t('screen.menu.demos.carousel_demo'), '🎞️');
            $submenu->screen(TextareaDemo::class);
            $submenu->screen(SplitDemo::class);
            $submenu->screen(TabsDemo::class);
        }, '🎮');
    }
}
