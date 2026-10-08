<?php

namespace Idei\Usim\Contracts;

use ArrayAccess;
use Idei\Usim\Contracts\Concerns\ArrayAccessibleDto;

/**
 * Result DTO for user synchronization operations.
 *
 * @phpstan-consistent-constructor
 *
 * @implements ArrayAccess<string, mixed>
 */
readonly class UserSyncResult implements ArrayAccess, SyncResultInterface
{
    use ArrayAccessibleDto;

    /**
     * @param  list<string>  $errors
     */
    final public function __construct(
        public int $usersCreated = 0,
        public int $usersUpdated = 0,
        public int $usersDeleted = 0,
        public bool $skipped = false,
        public string $skipReason = '',
        public array $errors = []
    ) {}

    public static function skipped(string $reason): static
    {
        return new static(
            skipped: true,
            skipReason: $reason
        );
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function getSkipReason(): ?string
    {
        return $this->skipReason !== '' ? $this->skipReason : null;
    }

    public function isSuccess(): bool
    {
        return ! $this->skipped && empty($this->errors);
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array{users_created: int, users_updated: int, users_deleted: int, skipped: bool, skip_reason: string, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'users_created' => $this->usersCreated,
            'users_updated' => $this->usersUpdated,
            'users_deleted' => $this->usersDeleted,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
            'errors' => $this->errors,
        ];
    }
}
