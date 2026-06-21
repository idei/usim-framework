<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UI;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Button;
use Idei\Usim\ValueObjects\Size;

class FormDemo extends Screen
{
    protected Label $lbl_instruction;
    protected Input $input_name;
    protected Input $input_email;
    protected Button $btn_submit;
    protected Label $lbl_result;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.form_demo.title'))
            ->maxWidth(Size::px(500))
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $container->add(
            UI::label('lbl_instruction')
                ->text(t('screen.demo.form_demo.instruction'))
                ->style('info')
        );

        $container->add(
            UI::input('input_name')
                ->label(t('screen.demo.form_demo.name.label'))
                ->placeholder(t('screen.demo.form_demo.name.placeholder'))
                ->value('')
                ->required(true)
                ->type('text')
                ->width(Size::full())
        );

        $container->add(
            UI::input('input_email')
                ->label(t('screen.demo.form_demo.email.label'))
                ->placeholder(t('screen.demo.form_demo.email.placeholder'))
                ->value('')
                ->required(true)
                ->type('email')
                ->width(Size::full())
        );

        $container->add(
            UI::button('btn_submit')
                ->label(t('screen.demo.form_demo.actions.submit'))
                ->action('submit_form')
                ->style('primary')
        );

        $container->add(
            UI::label('lbl_result')
                ->text(t('screen.demo.form_demo.result.initial'))
                ->style('secondary')
                ->width(Size::full())
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
