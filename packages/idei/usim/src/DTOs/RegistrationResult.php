<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Contracts\UsimUserInterface;
use Idei\Usim\Enums\AuthStatus;

final readonly class RegistrationResult
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public AuthStatus $status,
        public string $message = '',
        public ?string $token = null,
        public ?UsimUserInterface $user = null,
        public array $errors = [],
        public array $data = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === AuthStatus::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(
        string $message,
        ?UsimUserInterface $user = null,
        ?string $token = null,
        array $data = []
    ): self {
        return new self(
            status: AuthStatus::SUCCESS,
            message: $message,
            token: $token,
            user: $user,
            data: $data,
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function failed(
        string $message,
        array $errors = [],
        AuthStatus $status = AuthStatus::ERROR
    ): self {
        return new self(
            status: $status,
            message: $message,
            errors: $errors,
        );
    }
}
