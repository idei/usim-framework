<?php

namespace Idei\Usim\DTOs;

final readonly class RegisterData
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $passwordConfirmation,
        public array $roles = ['registered'],
        public ?string $unit = null,
        public bool $sendVerificationEmail = true,
        public bool $acceptTerms = true,
    ) {}
}
