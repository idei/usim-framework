<?php

namespace App\UI\Screens\Testing;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class CustomSlugFramedScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $menuScreen = 'admin/admin-menu';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::label('slug_screen_title')->text('Slug Screen Content'));
    }
}
