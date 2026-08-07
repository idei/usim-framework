<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class ButtonDemo extends Screen
{
    protected Button $btn_toggle;
    protected bool $store_state = false;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->alignContent('center')->alignItems('center')
            ->title(t('screen.demo.button_demo.title'))
            ->padding(Spacing::px(30))->maxWidth(Size::px(400))
            ->centerHorizontal()->shadow(2)
            ->add(
                UI::button('btn_toggle')
                    ->label(t('screen.demo.button_demo.default'))
                    ->action('toggle_label')
                    ->style('primary')
            );
    }

    protected function postLoadUI(): void
    {
        $this->updateButtonState();
    }

    /** @param array<string, mixed> $params */
    public function onToggleLabel(array $params): void
    {
        $this->store_state = !$this->store_state;
        $this->updateButtonState();
    }

    private function updateButtonState(): void
    {
        if ($this->store_state) {
            $this->btn_toggle->label(t('screen.demo.button_demo.clicked'))->style('success');
        } else {
            $this->btn_toggle->label(t('screen.demo.button_demo.default'))->style('primary');
        }
    }
}
