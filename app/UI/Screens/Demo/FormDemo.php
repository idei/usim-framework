<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class FormDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Label $lbl_instruction;
    protected Input $input_name;
    protected Input $input_email;
    protected Textarea $input_message;
    protected Button $btn_submit;
    protected Label $lbl_result;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container()
            ->card()
            ->maxWidth(Size::px(500))
            ->centerHorizontal()
            ->padding(Spacing::px(10));

        $wrapper->add(
            $this->lbl_instruction
                ->text(t('screens.demo.form_demo.instruction'))
                ->center()
                ->style('h3')
        );

        $wrapper->add(
            $this->input_name
                ->label(t('screens.demo.form_demo.name.label'))
                ->placeholder(t('screens.demo.form_demo.name.placeholder'))
                ->value('')
                ->required(true)
                ->type('text')
                ->width(Size::full())
        );

        $wrapper->add(
            $this->input_email
                ->label(t('screens.demo.form_demo.email.label'))
                ->placeholder(t('screens.demo.form_demo.email.placeholder'))
                ->value('')
                ->required(true)
                ->type('email')
                ->width(Size::full())
        );
        $wrapper->add(
            $this->input_message
                ->label(t('screens.demo.form_demo.message.label'))
                ->placeholder(t('screens.demo.form_demo.message.placeholder'))
                ->value('Una situación pendular...\n\tY poco convencional.')
                ->required(true)
                ->width(Size::full())
        );

        $wrapper->add(
            $this->btn_submit
                ->label(t('screens.demo.form_demo.actions.submit'))
                ->action('submit_form')
                ->style('primary')
        );

        $wrapper->add(
            $this->lbl_result
                ->text(t('screens.demo.form_demo.result.initial'))
                ->style('secondary')
                ->width(Size::full())
        );

        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        $this->input_name->value("")->error(null);
        $this->input_email->value("")->error(null);
        $this->input_message->value("")->error(null);
        $this->lbl_result
            ->text(t('screens.demo.form_demo.result.initial'))
            ->style('secondary');
    }

    /**
     * Handle form submission with validation
     * Reads input values from frontend parameters (sent by collectContextValues)
     */
    /** @param array<string, mixed> $params */
    public function onSubmitForm(array $params): void
    {
        // Get input values from frontend parameters (sent by collectContextValues)
        $rawName = $params['input_name'] ?? '';
        $name = trim(is_scalar($rawName) || $rawName instanceof \Stringable ? (string) $rawName : '');

        $rawEmail = $params['input_email'] ?? '';
        $email = trim(is_scalar($rawEmail) || $rawEmail instanceof \Stringable ? (string) $rawEmail : '');

        $rawMessage = $params['input_message'] ?? '';
        $message = trim(is_scalar($rawMessage) || $rawMessage instanceof \Stringable ? (string) $rawMessage : '');

        // Clear previous errors
        $this->input_name->error(null);
        $this->input_email->error(null);
        $this->input_message->error(null);

        // Validation flags
        $hasErrors = false;

        // Validate name
        if (empty($name)) {
            $this->input_name->error(t('screens.demo.form_demo.validation.name_required'));
            $hasErrors = true;
        } elseif (strlen($name) < 2) {
            $this->input_name->error(t('screens.demo.form_demo.validation.name_min'));
            $hasErrors = true;
        }

        // Validate email
        if (empty($email)) {
            $this->input_email->error(t('screens.demo.form_demo.validation.email_required'));
            $hasErrors = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->input_email->error(t('screens.demo.form_demo.validation.email_invalid'));
            $hasErrors = true;
        }

        // Show result
        if ($hasErrors) {
            $this->lbl_result
                ->text(t('screens.demo.form_demo.result.errors'))
                ->style('danger');
            $this->toast(t('screens.demo.form_demo.toast.correct_errors'), 'error');
        } else {
            $this->lbl_result
                ->text(t('screens.demo.form_demo.result.success', [
                    'name' => $name,
                    'email' => $email,
                    'message' => $message
                ]))
                ->style('success');

            // Clear form inputs after successful submission
            $this->input_name->value('');
            $this->input_email->value('');
            $this->input_message->value('');
        }
    }
}
