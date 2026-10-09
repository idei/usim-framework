<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Enums\AuthStatus;

final readonly class PasswordResetResult
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public AuthStatus $status,
        public string $message = '',
        public array $errors = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === AuthStatus::SUCCESS;
    }

    public static function success(string $message): self
    {
        return new self(
            status: AuthStatus::SUCCESS,
            message: $message,
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function failed(string $message, array $errors = []): self
    {
        return new self(
            status: AuthStatus::ERROR,
            message: $message,
            errors: $errors,
        );
    }
}
