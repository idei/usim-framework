<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\DTOs\AuthResult;
use Idei\Usim\DTOs\LoginCredentials;

interface LoginActionInterface
{
    public function execute(LoginCredentials $credentials, bool $startSession = true): AuthResult;
}
