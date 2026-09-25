<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class ButtonDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected const string KEY_PREFIX = 'screen.demo.container_demo.';

    protected Button $first;
    protected Button $second;
    protected Button $third;
    protected Container $message_container;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->message_container = UI::container('message_container')
            ->backgroundColor('#f0f0f0')->borderRadius(Spacing::px(10))
            ->width(Size::pct(100))
            ->padding(Spacing::px(5));

        $container
            ->alignContent('center')->alignItems('center')
            ->title(t(self::KEY_PREFIX . 'title'))
            ->padding(Spacing::px(10))->maxWidth(Size::px(600))
            ->centerHorizontal()->shadow(2)
            ->gap(Spacing::px(20))
            ->add($this->buttonsContainer())
            ->add($this->message_container);
    }

    private function buttonsContainer(): Container
    {
        return UI::container('buttons_container')
            ->layout(LayoutType::HORIZONTAL)
            ->alignContent('center')
            ->alignItems('center')
            ->gap(Spacing::px(10))
            ->plain()
            ->add(
                UI::button('first')
                    ->label(t(self::KEY_PREFIX . 'first'))
                    ->status(true)
                    ->action('first')
            )->add(
                UI::button('second')
                    ->label(t(self::KEY_PREFIX . 'second'))
                    ->status(false)
                    ->action('second')
            )->add(
                UI::button('third')
                    ->label(t(self::KEY_PREFIX . 'third'))
                    ->status(false)
                    ->action('third')
            );
    }

    protected function postLoadUI(): void
    {
        $this->updateButtonState();
        $this->message_container->clear();
        $this->build(CheckboxDemo::class, $this->message_container);
    }

    /** @param array<string, mixed> $params */
    public function onFirst(array $params): void
    {
        if ($this->first->getStatus()) {
            return;
        }

        $this->first->status(true);
        $this->second->status(false);
        $this->third->status(false);
        $this->updateButtonState();
        $this->message_container->clear();
        $this->build(CheckboxDemo::class, $this->message_container);
    }

    public function onSecond(array $params): void
    {
        if ($this->second->getStatus()) {
            return;
        }

        $this->first->status(false);
        $this->second->status(true);
        $this->third->status(false);
        $this->updateButtonState();
        $this->message_container->clear();
        $this->build(FormDemo::class, $this->message_container);
    }

    public function onThird(array $params): void
    {
        if ($this->third->getStatus()) {
            return;
        }

        $this->first->status(false);
        $this->second->status(false);
        $this->third->status(true);
        $this->updateButtonState();
        $this->message_container->clear();
        $this->build(TabsDemo::class, $this->message_container);
    }

    private function updateButtonState(): void
    {
        $this->setStatusStyle($this->first);
        $this->setStatusStyle($this->second);
        $this->setStatusStyle($this->third);
    }

    private function setStatusStyle(Button $button): void
    {
        $status = $button->getStatus() ? 'success' : 'secondary';
        $button->style($status);
    }
}
