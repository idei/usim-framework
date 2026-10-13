<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class About extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    private const int WIDTH = 600;
    private const int HEIGHT = 400;

    protected Label $lbl_title;
    protected Label $lbl_message;
    protected Button $close_button;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container()
            ->card()
            ->padding(Spacing::px(8))
            ->centerHorizontal()
            ->maxWidth(Size::px(self::WIDTH))->minWidth(Size::px(self::WIDTH))
            ->minHeight(Size::px(self::HEIGHT))->maxHeight(Size::px(self::HEIGHT));

        $wrapper->add(
            $this->lbl_title
                ->text(t('screen.about.title'))
                ->center()
                ->style('h3')
        );

        $wrapper->add(
            $this->lbl_message
                ->html('about.about-en')
                ->center()
                ->style('p')
                ->minHeight(Size::px(self::HEIGHT - 100))
        );

        $wrapper->add(
            $this->close_button
                ->label(t('screen.about.close'))
                ->style('primary')
                ->action('close_modal')
        );

        $container->add($wrapper);
    }

    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }
}
