<?php

namespace Idei\Usim\Contracts;

use ArrayAccess;
use Idei\Usim\Contracts\Concerns\ArrayAccessibleDto;

/**
 * Result DTO for device synchronization operations.
 *
 * @phpstan-consistent-constructor
 *
 * @implements ArrayAccess<string, mixed>
 */
readonly class DeviceSyncResult implements ArrayAccess, SyncResultInterface
{
    use ArrayAccessibleDto;

    /**
     * @param  list<string>  $synced
     * @param  list<string>  $errors
     */
    final public function __construct(
        public array $synced = [],
        public array $errors = [],
        public bool $skipped = false,
        public string $skipReason = ''
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
     * @return array{synced: list<string>, errors: list<string>, skipped: bool, skip_reason: string}
     */
    public function toArray(): array
    {
        return [
            'synced' => $this->synced,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
        ];
    }
}
