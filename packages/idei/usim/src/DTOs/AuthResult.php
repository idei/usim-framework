<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Contracts\UsimUserInterface;
use Idei\Usim\Enums\AuthStatus;
use Idei\Usim\Screen;

final readonly class AuthResult
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $data
     * @param  class-string<Screen>|null  $homeScreen
     */
    public function __construct(
        public AuthStatus $status,
        public string $message = '',
        public ?string $token = null,
        public ?string $redirectTo = null,
        public ?UsimUserInterface $user = null,
        public ?string $homeScreen = null,
        public ?string $activeUnit = null,
        public array $errors = [],
        public array $data = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === AuthStatus::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  class-string<Screen>|null  $homeScreen
     */
    public static function success(
        string $redirectTo,
        ?string $token = null,
        string $message = '',
        ?UsimUserInterface $user = null,
        ?string $homeScreen = null,
        ?string $activeUnit = null,
        array $data = []
    ): self {
        return new self(
            status: AuthStatus::SUCCESS,
            message: $message,
            token: $token,
            redirectTo: $redirectTo,
            user: $user,
            homeScreen: $homeScreen,
            activeUnit: $activeUnit,
            data: $data,
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function failed(
        AuthStatus $status = AuthStatus::BAD_CREDENTIALS,
        string $message = '',
        array $errors = []
    ): self {
        return new self(
            status: $status,
            message: $message,
            errors: $errors,
        );
    }
}
