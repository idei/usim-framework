<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class Home extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->padding(Spacing::px(0));
        $container->add(
            UI::label('welcome_usim')
                ->html('welcome-usim', ['title' => config('usim.app_name')])
                ->width(Size::full())
        )->plain();
    }
}
