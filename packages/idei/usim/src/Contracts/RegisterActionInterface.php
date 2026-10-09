<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\DTOs\RegisterData;
use Idei\Usim\DTOs\RegistrationResult;

interface RegisterActionInterface
{
    public function execute(RegisterData $data): RegistrationResult;
}
