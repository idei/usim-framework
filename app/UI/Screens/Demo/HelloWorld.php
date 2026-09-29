<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class HelloWorld extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

	protected function buildBaseUI(Container $container, ...$params): void
	{
        $container->padding(Spacing::px(20))
            ->centerHorizontal()
            ->maxWidth(Size::px(600))
            ->minHeight(Size::px(400))
            ->shadow(2);

        $container->add(
            UI::label("label")
                ->text("Hello HelloWorld!")
                ->style('h1')
        );
	}
}
