<?php

namespace App\UI\Requests\Device;

use Idei\Usim\Http\Requests\UIRequest;

class ApprovePairingRequest extends UIRequest
{
    public protected(set) string $pin = '';

    protected array $map = [
        'pin' => 'input_pin',
    ];

    public function rules(): array
    {
        return [
            'pin' => 'required|digits:4',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => 'El PIN debe tener exactamente 4 dígitos.',
            'pin.required' => 'El PIN es obligatorio.',
        ];
    }
}
