<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\LabelBuilder;

class DemoUi extends AbstractUIService
{
    protected LabelBuilder $lbl_welcome;
    protected LabelBuilder $lbl_counter;
    protected LabelBuilder $lbl_new_components;
    protected UIContainer $new_components_container;
    protected int $store_counter = 1000;
    protected int $store_new_components = 0;
    protected array $store_dynamic_buttons = [];

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title('Demo UI Components')
            ->maxWidth('500px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $this->buildUIElements($container);
    }

    private function buildUIElements($container): void
    {
        $container->add(
            UIBuilder::button('reset_button')
                ->label('🔄 Reset State')
                ->action('reset_state')
                ->icon('refresh')
                ->style('secondary')
                ->variant('outlined')
        );
        $container->add(
            UIBuilder::label('lbl_welcome')
                ->text('🔵 Initial State: Press "Test Update" to change this text')
                ->style('info')
        );

        $container->add(
            UIBuilder::button('btn_test_update')
                ->label('🔄 Test Update')
                ->action('test_action')
                ->icon('star')
                ->style('primary')
                ->variant('filled')
        );

        $container->add(
            UIBuilder::button('btn_test_add')
                ->label('➕ Test Add')
                ->action('add_new_component')
                ->icon('settings')
                ->style('warning')
                ->variant('filled')
        );

        $container->add(
            UIBuilder::label()
                ->text('🔢 Counter')
                ->style('default')
        );

        $counterContainer = UIBuilder::container('counter_container')
            ->layout(LayoutType::HORIZONTAL)
            ->shadow(false)
            ->centerContent()
            ->gap("10px");

        $counterContainer->add(
            UIBuilder::button('btn_decrement')
                ->label('➖')
                ->action('decrement_counter')
                ->style('danger')
                ->variant('filled')
        );

        $counterContainer->add(
            UIBuilder::label('lbl_counter')
                ->text($this->store_counter)
                ->style('primary')
        );

        $counterContainer->add(
            UIBuilder::button('btn_increment')
                ->label('➕')
                ->action('increment_counter')
                ->style('success')
                ->variant('filled')
        );

        $container->add($counterContainer);

        $container->add(
            UIBuilder::label()
                ->text('💡 New buttons will be added below when you press "Test Add"')
                ->style('default')
        );

        /**
         * Container for new dynamically added components that will be arranged
         * horizontally until the width is filled, then continue on the next row.
         * Components will have a 10px horizontal and vertical gap, and no shadow
         * to maintain a clean design.
         */
        $this->new_components_container = UIBuilder::container('new_components_container')
            ->layout(LayoutType::GRID)
            ->shadow(false)
            ->border(true)
            ->padding('10px')
            ->gap('10px');

        $container->add($this->new_components_container);

        $this->lbl_new_components = UIBuilder::label('lbl_new_components');
        $this->updateDynamicButtonsLabel();
        $container->add($this->lbl_new_components);
    }

    protected function postLoadUI(): void
    {
        $this->updateCounterLabel($this->lbl_counter, $this->store_counter);
        $this->updateDynamicButtonsLabel();
    }

    public function onResetState(array $params): void
    {
        $this->store_counter = 1000;
        $this->store_new_components = 0;
        $this->store_dynamic_buttons = [];
        $this->lbl_welcome
            ->text('🔵 Initial State: Press "Test Update" to change this text')
            ->style('info');
        $this->updateCounterLabel($this->lbl_counter, $this->store_counter);
        $this->updateDynamicButtonsLabel();
    }

    public function onTestAction(array $params): void
    {
        $this->lbl_welcome
            ->text("✅ Pressed button!\n\nCurrent time: " . now()->toDateTimeString())
            ->style('success');
    }

    public function onIncrementCounter(array $params): void
    {
        $this->updateCounterLabel($this->lbl_counter, ++$this->store_counter);
    }

    public function onDecrementCounter(array $params): void
    {
        $this->updateCounterLabel($this->lbl_counter, --$this->store_counter);
    }

    private function updateCounterLabel(LabelBuilder $labelBuilder, int $counterValue): LabelBuilder
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

    public function onAddNewComponent(array $params): void
    {
        $added = false;
        $button_number = $this->store_new_components + 1;
        $new_button = UIBuilder::button("btn_new_button_$button_number")
            ->label("✨ Button $button_number");

        $new_button->action('new_button_action', [
            'id' => $new_button->getId(),
            'button_number' => $button_number
        ]);

        $this->new_components_container->add($new_button, $added);

        if ($added) {
            $this->store_new_components++;
            $this->store_dynamic_buttons[] = $new_button->getId();
            $this->updateDynamicButtonsLabel();
        }
    }

    public function onNewButtonAction(array $params): void
    {
        $buttonId = $params['id'] ?? 'unknown';
        $this->new_components_container->remove($buttonId);
        $this->store_dynamic_buttons = array_filter($this->store_dynamic_buttons, fn($v) => $v !== $buttonId);
        $this->updateDynamicButtonsLabel();
    }

    private function updateDynamicButtonsLabel(): void
    {
        if (empty($this->store_dynamic_buttons)) {
            $this->lbl_new_components
                ->text('No new components added yet.')
                ->style('default');
        } else {
            $str_buttons = implode(', ', $this->store_dynamic_buttons);
            $this->lbl_new_components
                ->text($str_buttons)
                ->style('success');
        }
    }
}
