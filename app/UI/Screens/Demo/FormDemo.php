<?php

namespace App\UI\Screens\Demo;

use App\UI\Requests\Demo\FormDemoRequest;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Contracts\Validation\Validator;

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
        $this->input_name->value('')->error(null);
        $this->input_email->value('')->error(null);
        $this->input_message->value('')->error(null);
        $this->lbl_result
            ->text(t('screens.demo.form_demo.result.initial'))
            ->style('secondary');
    }

    /**
     * Handle form submission using FormDemoRequest.
     */
    public function onSubmitForm(FormDemoRequest $request): void
    {
        $this->lbl_result
            ->text(t('screens.demo.form_demo.result.success', [
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message ?? '',
            ]))
            ->style('success');

        // Clear form inputs after successful submission
        $this->input_name->value('');
        $this->input_email->value('');
        $this->input_message->value('');
    }

    /**
     * Update result label when form validation fails.
     */
    public function onValidationFailed(UIRequest $request, Validator $validator): void
    {
        $this->lbl_result
            ->text(t('screens.demo.form_demo.result.errors'))
            ->style('danger');
    }
}
