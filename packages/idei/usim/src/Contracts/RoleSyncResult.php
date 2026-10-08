<?php

namespace Idei\Usim\Contracts;

use ArrayAccess;
use Idei\Usim\Contracts\Concerns\ArrayAccessibleDto;

/**
 * Result DTO for role and permission synchronization operations.
 *
 * @phpstan-consistent-constructor
 *
 * @implements ArrayAccess<string, mixed>
 */
readonly class RoleSyncResult implements ArrayAccess, SyncResultInterface
{
    use ArrayAccessibleDto;

    /**
     * @param  list<string>  $errors
     */
    final public function __construct(
        public int $permissionsCreated = 0,
        public int $rolesCreated = 0,
        public int $rolesUpdated = 0,
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
     * @return array{permissions_created: int, roles_created: int, roles_updated: int, skipped: bool, skip_reason: string, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'permissions_created' => $this->permissionsCreated,
            'roles_created' => $this->rolesCreated,
            'roles_updated' => $this->rolesUpdated,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
            'errors' => $this->errors,
        ];
    }
}
