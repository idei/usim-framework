<?php
// @usim: feature="core", type="screen"
namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

class Home extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $config = app(UsimConfig::class);

        $container->add(
            UI::label('welcome_usim')
                ->html(
                    'welcome-usim',
                    ['title' => $config->appName]
                )
                ->width(Size::full())
        )->plain();
    }
}
