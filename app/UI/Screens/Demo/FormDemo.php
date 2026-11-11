<?php

namespace App\UI\Screens\Demo;

use App\UI\Requests\Demo\FormDemoRequest;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Enums\Style;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Illuminate\Contracts\Validation\Validator;

class FormDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $lbl_instruction;

    protected Input $input_name;

    protected Input $input_email;

    protected Textarea $input_message;

    protected Button $btn_submit;

    protected Button $btn_reset;

    protected NewLabel $lbl_result;

    protected function resetUI(): void
    {
        $this->input_name->value('')->error(null);
        $this->input_email->value('')->error(null);
        $this->input_message->value('')->error(null);
        $this->lbl_result
            ->text(t('screens.demo.form_demo.result.initial'))
            ->style(Style::SECONDARY);
    }

    /**
     * Handle form submission using FormDemoRequest.
     */
    public function onSubmitForm(FormDemoRequest $request): void
    {
        $this->lbl_result
            ->text(t('.result.success', [
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
     * Reset form fields and validation state to initial defaults.
     */
    public function onResetForm(): void
    {
        $this->resetUI();
    }

    /**
     * Update result label when form validation fails.
     */
    public function onValidationFailed(UIRequest $request, Validator $validator): void
    {
        $this->lbl_result
            ->text(t('.result.errors'))
            ->style(Style::DANGER);
    }
}
