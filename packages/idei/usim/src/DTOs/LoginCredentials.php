<?php

namespace Idei\Usim\DTOs;

final readonly class LoginCredentials
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
        public ?string $unit = null,
    ) {}
}

