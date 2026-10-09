<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\DTOs\PasswordResetResult;

interface PasswordResetActionInterface
{
    public function sendResetLink(string $email): PasswordResetResult;

    public function resetPassword(
        string $token,
        string $email,
        string $password,
        string $passwordConfirmation
    ): PasswordResetResult;
}

