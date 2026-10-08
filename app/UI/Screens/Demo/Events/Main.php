<?php

namespace App\UI\Screens\Demo\Events;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class Main extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Container $left_pane;
    protected Container $right_pane;
    protected Split $events_split;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('events_demo_wrapper')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(600))
            ->minWidth(Size::px(600))
            ->centerHorizontal()
            ->padding(Spacing::px(5));

        $this->left_pane = UI::container('left_pane')
            ->padding(Spacing::px(5))
            ->width(Size::full());

        $this->right_pane = UI::container('right_pane')
            ->padding(Spacing::px(5))
            ->width(Size::full());

        $this->events_split = UI::split('events_split')
            ->vertical()
            ->splitSize('25%')
            ->minFirstSize('30px')
            ->minSecondSize('220px')
            ->width(Size::full())
            ->gap(Spacing::px(5))
            ->padding(Spacing::px(5))
            ->addFirst($this->left_pane)
            ->addSecond($this->right_pane);

        $wrapper->add($this->events_split);

        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        $this->left_pane->embed(Left::class);
        $this->right_pane->embed(Right::class);
    }
}
