<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UIBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Components\InputBuilder;
use Idei\Usim\Components\LabelBuilder;
use Idei\Usim\Components\Button;

class FormDemo extends Screen
{
    protected LabelBuilder $lbl_instruction;
    protected InputBuilder $input_name;
    protected InputBuilder $input_email;
    protected Button $btn_submit;
    protected LabelBuilder $lbl_result;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.form_demo.title'))
            ->maxWidth('500px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $container->add(
            UIBuilder::label('lbl_instruction')
                ->text(t('screen.demo.form_demo.instruction'))
                ->style('info')
        );

        $container->add(
            UIBuilder::input('input_name')
                ->label(t('screen.demo.form_demo.name.label'))
                ->placeholder(t('screen.demo.form_demo.name.placeholder'))
                ->value('')
                ->required(true)
                ->type('text')
                ->width('100%')
        );

        $container->add(
            UIBuilder::input('input_email')
                ->label(t('screen.demo.form_demo.email.label'))
                ->placeholder(t('screen.demo.form_demo.email.placeholder'))
                ->value('')
                ->required(true)
                ->type('email')
                ->width('100%')
        );

        $container->add(
            UIBuilder::button('btn_submit')
                ->label(t('screen.demo.form_demo.actions.submit'))
                ->action('submit_form')
                ->style('primary')
        );

        $container->add(
            UIBuilder::label('lbl_result')
                ->text(t('screen.demo.form_demo.result.initial'))
                ->style('secondary')
        );
    }

    protected function postLoadUI(): void
    {
        $this->input_name->value("")->error(null);
        $this->input_email->value("")->error(null);
        $this->lbl_result
            ->text(t('screen.demo.form_demo.result.initial'))
            ->style('secondary');
    }

    /**
     * Handle form submission with validation
     * Reads input values from frontend parameters (sent by collectContextValues)
     */
    public function onSubmitForm(array $params): void
    {
        // Get input values from frontend parameters (sent by collectContextValues)
        $name = trim($params['input_name'] ?? '');
        $email = trim($params['input_email'] ?? '');

        // Clear previous errors
        $this->input_name->error(null);
        $this->input_email->error(null);

        // Validation flags
        $hasErrors = false;

        // Validate name
        if (empty($name)) {
            $this->input_name->error(t('screen.demo.form_demo.validation.name_required'));
            $hasErrors = true;
        } elseif (strlen($name) < 2) {
            $this->input_name->error(t('screen.demo.form_demo.validation.name_min'));
            $hasErrors = true;
        }

        // Validate email
        if (empty($email)) {
            $this->input_email->error(t('screen.demo.form_demo.validation.email_required'));
            $hasErrors = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->input_email->error(t('screen.demo.form_demo.validation.email_invalid'));
            $hasErrors = true;
        }

        // Show result
        if ($hasErrors) {
            $this->lbl_result
                ->text(t('screen.demo.form_demo.result.errors'))
                ->style('danger');
            $this->toast(t('screen.demo.form_demo.toast.correct_errors'), 'error');
        } else {
            $this->lbl_result
                ->text(t('screen.demo.form_demo.result.success', ['name' => $name, 'email' => $email]))
                ->style('success');

            // Clear form inputs after successful submission
            $this->input_name->value('');
            $this->input_email->value('');
        }
    }
}
