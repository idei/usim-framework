<?php

namespace App\UI\Screens\Testing;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class CustomKioskScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::label('kiosk_title')->text('Standalone Kiosk'));
    }
}
