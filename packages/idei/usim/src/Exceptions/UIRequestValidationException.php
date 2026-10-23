<?php

namespace Idei\Usim\Exceptions;

use Idei\Usim\Http\Requests\UIRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a UIRequest fails validation.
 */
class UIRequestValidationException extends ValidationException
{
    public function __construct(
        Validator $validator,
        public readonly ?UIRequest $uiRequest = null,
        ?Response $response = null,
        string $errorBag = 'default'
    ) {
        parent::__construct($validator, $response, $errorBag);
    }
}
