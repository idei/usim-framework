<?php

namespace App\UI\Requests\Demo;

use Idei\Usim\Http\Requests\UIRequest;

/**
 * @property string $name
 * @property string $email
 * @property string|null $message
 */
class FormDemoRequest extends UIRequest
{
    public protected(set) string $name = '';

    public protected(set) string $email = '';

    public protected(set) ?string $message = null;

    /**
     * Map clean attribute names to component input names.
     *
     * @var array<string, string>
     */
    protected array $map = [
        'name' => 'input_name',
        'email' => 'input_email',
        'message' => 'input_message',
    ];

    /**
     * Sanitize inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $rawName = $this->input('name');
        $rawEmail = $this->input('email');
        $rawMessage = $this->input('message');

        $this->merge([
            'name' => trim(is_scalar($rawName) || $rawName instanceof \Stringable ? (string) $rawName : ''),
            'email' => trim(is_scalar($rawEmail) || $rawEmail instanceof \Stringable ? (string) $rawEmail : ''),
            'message' => trim(is_scalar($rawMessage) || $rawMessage instanceof \Stringable ? (string) $rawMessage : ''),
        ]);
    }

    /**
     * Validation rules for FormDemo submission.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|min:2',
            'email' => 'required|email',
            'message' => 'nullable|string',
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => t('screens.demo.form_demo.validation.name_required'),
            'name.min' => t('screens.demo.form_demo.validation.name_min'),
            'email.required' => t('screens.demo.form_demo.validation.email_required'),
            'email.email' => t('screens.demo.form_demo.validation.email_invalid'),
        ];
    }

    /**
     * Toast notification shown when validation fails.
     */
    public function errorToastMessage(): ?string
    {
        return t('screens.demo.form_demo.toast.correct_errors');
    }
}
