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
        $this->left_pane = UI::container('left_pane')
            ->padding(Spacing::px(20))
            ->width(Size::full());

        $this->right_pane = UI::container('right_pane')
            ->padding(Spacing::px(20))
            ->width(Size::full());

        $this->events_split = UI::split('events_split')
            ->vertical()
            ->splitSize('25%')
            ->minFirstSize('30px')
            ->minSecondSize('220px')
            ->width(Size::full())
            ->gap(Spacing::px(20))
            ->padding(Spacing::px(20))
            ->addFirst($this->left_pane)
            ->addSecond($this->right_pane);

        $container
            ->title('Comunicación entre Screens con Eventos')
            ->maxWidth(Size::px(500))
            ->centerHorizontal()
            ->padding(Spacing::px(20))
            ->width(Size::full())
            ->add($this->events_split);
    }

    protected function postLoadUI(): void
    {
        $this->left_pane->embed(Left::class);
        $this->right_pane->embed(Right::class);
    }
}

