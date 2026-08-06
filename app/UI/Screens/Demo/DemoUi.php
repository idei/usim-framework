<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;


class DemoUi extends Screen
{
    protected Label $lbl_welcome;
    protected Label $lbl_counter;
    protected Container $new_components_container;
    protected int $store_counter = 1000;
    protected int $store_new_components = 0;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.demo_ui.title'))
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->rounded(false)
            ->padding(Spacing::px(10));

        $this->buildUIElements($container);
    }

    private function buildUIElements(Container $container): void
    {
        $container->add(
            UI::button('reset_button')
                ->label(t('screen.demo.demo_ui.actions.reset_state'))
                ->action('reset_state')
                ->icon('refresh')
                ->style('secondary')
                ->variant('outlined')
        );
        $container->add(
            UI::label('lbl_welcome')
                ->text(t('screen.demo.demo_ui.welcome.initial'))
                ->style('info')
        );

        $container->add(
            UI::button('btn_test_update')
                ->label(t('screen.demo.demo_ui.actions.test_update'))
                ->action('test_action')
                ->icon('star')
                ->style('primary')
                ->variant('filled')
        );

        $container->add(
            UI::label()
                ->text(t('screen.demo.demo_ui.counter.label'))
                ->style('default')
        );

        $counterContainer = UI::container('counter_container')
            ->layout(LayoutType::HORIZONTAL)
            ->shadow(false)
            ->centerContent()
            ->gap(Spacing::px(10));

        $counterContainer->add(
            UI::button('btn_decrement')
                ->label(t('screen.demo.demo_ui.actions.decrement'))
                ->action('decrement_counter')
                ->style('danger')
                ->variant('filled')
        );

        $counterContainer->add(
            UI::label('lbl_counter')
                ->text((string) $this->store_counter)
                ->style('primary')
        );

        $counterContainer->add(
            UI::button('btn_increment')
                ->label(t('screen.demo.demo_ui.actions.increment'))
                ->action('increment_counter')
                ->style('success')
                ->variant('filled')
        );

        $container->add($counterContainer);

        $container->add(
            UI::button('btn_test_add')
                ->label(t('screen.demo.demo_ui.actions.test_add'))
                ->action('add_new_component')
                ->icon('settings')
                ->style('warning')
                ->variant('filled')
        );

        $container->add(
            UI::label()
                ->text(t('screen.demo.demo_ui.hint.add_buttons'))
                ->style('default')
        );

        $this->new_components_container = UI::container('new_components_container')
            ->layout(LayoutType::GRID)
            ->rounded(false)
            ->gridTemplateColumns('repeat(auto-fill, minmax(150px, 150px))')
            ->gridTemplateRows('repeat(auto-fill, minmax(40px, 40px))')
            ->minHeight(Size::px(100))
            ->justifyContent('start')
            ->fullWidth()
            ->padding(Spacing::px(5))
            ->gap(Spacing::px(5));

        $container->add($this->new_components_container);
    }

    protected function postLoadUI(): void
    {
        $this->updateCounterLabel($this->lbl_counter, $this->store_counter);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onResetState(array $params): void
    {
        $this->store_counter = 1000;
        $this->store_new_components = 0;
        $this->lbl_welcome
            ->text(t('screen.demo.demo_ui.welcome.initial'))
            ->style('info');
        $this->new_components_container->clear();
        $this->updateCounterLabel($this->lbl_counter, $this->store_counter);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onTestAction(array $params): void
    {
        $this->lbl_welcome
            ->text(t('screen.demo.demo_ui.welcome.updated', ['time' => now()->toDateTimeString()]))
            ->style('success');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onIncrementCounter(array $params): void
    {
        $this->updateCounterLabel($this->lbl_counter, ++$this->store_counter);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDecrementCounter(array $params): void
    {
        $this->updateCounterLabel($this->lbl_counter, --$this->store_counter);
    }

    private function updateCounterLabel(Label $labelBuilder, int $counterValue): Label
    {
        $counterStyle = 'primary';
        if ($counterValue > 5) {
            $counterStyle = 'success';
        } elseif ($counterValue < 0) {
            $counterStyle = 'danger';
        }

        $labelBuilder
            ->text((string) $counterValue)
            ->style($counterStyle);

        return $labelBuilder;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onAddNewComponent(array $params): void
    {
        $added = false;
        $button_number = $this->store_new_components + 1;
        $new_button = UI::button("btn_new_button_$button_number")
            ->label(t('screen.demo.demo_ui.dynamic.button_label', ['number' => $button_number]))
            ->style('info');

        $new_button->action('new_button_action', [
            'id' => $new_button->getId(),
            'button_number' => $button_number
        ]);

        $this->new_components_container->add($new_button, $added);

        if ($added) {
            $this->store_new_components++;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onNewButtonAction(array $params): void
    {
        $button = $this->findRootComponentAs($params['id'] ?? null, Button::class);

        if (!$button) {
            return;
        }

        $style = $button->get('style') !== 'warning' ? 'warning' : 'info';
        $button->style($style);

        //$this->new_components_container->remove((int) ($params['id'] ?? 0));
    }
}
