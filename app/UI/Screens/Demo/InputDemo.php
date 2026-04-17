<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UIBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\LabelBuilder;

/**
 * Input Demo Service
 *
 * Demonstrates input component functionality:
 * - Text input with placeholder
 * - Reading input value from frontend
 * - Updating input value from backend
 * - Label updates based on input
 * - Error state with tooltip
 *
 * Uses Screen for automatic event lifecycle management.
 * Event handlers only need to modify components, no return needed.
 */
class InputDemo extends Screen
{
    protected Input $input_text;
    protected LabelBuilder $lbl_result;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.input_demo.title'))
            ->maxWidth('500px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $container->add(
            UIBuilder::label('lbl_instruction')
                ->text(t('screen.demo.input_demo.instruction'))
                ->style('info')
                ->width('100%')
        );

        $container->add(
            UIBuilder::input('input_text')
                ->label(t('screen.demo.input_demo.name.label'))
                ->placeholder(t('screen.demo.input_demo.name.placeholder'))
                ->value('')
                ->required(true)
                ->type('text')
                ->width('100%')
        );

        $container->add(
            UIBuilder::button('btn_get_value')
                ->label(t('screen.demo.input_demo.actions.validate'))
                ->action('get_value')
                ->style('primary')
        );

        $container->add(
            UIBuilder::label('lbl_result')
                ->text(t('screen.demo.input_demo.result.initial'))
                ->style('default')
                ->width('100%')
        );
    }

    protected function postLoadUI(): void
    {
        $this->input_text->value('')->error(null);
        $this->lbl_result
            ->text(t('screen.demo.input_demo.result.initial'))
            ->style('default');
    }

    /**
     * Handle "Validate" button click
     *
     * Validates the input and shows errors using the error() method with tooltip.
     * No return needed - Screen handles diff calculation and response.
     *
     * @param array $params Event parameters (should include 'input_text' from input)
     * @return void
     */
    public function onGetValue(array $params): void
    {
        $inputValue = trim($params['input_text'] ?? '');

        // Clear previous error
        $this->input_text->error(null);

        if (empty($inputValue)) {
            $this->displayError(t('screen.demo.input_demo.validation.name_required'));
        } elseif (\strlen($inputValue) < 3) {
            $this->displayError(t('screen.demo.input_demo.validation.name_min'));
        } else {
            $this->lbl_result->text(t('screen.demo.input_demo.result.valid_name', ['name' => $inputValue]))->style('success');
        }
    }

    private function displayError(string $message): void
    {
        $this->input_text->error($message);
        $this->toast($message, 'error');
        $this->lbl_result->text(t('screen.demo.input_demo.result.fix_error'))->style('danger');
    }
}
