<?php
// @usim: feature="admin", type="screen", as-class="Home"
namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

class HomeStub extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $config = app(UsimConfig::class);

        $container->add(
            UI::label('landing_page_welcome')
                ->html(
                    'landing',
                    ['title' => $config->appName]
                )
                ->width(Size::full())
        )->plain();
    }
}
