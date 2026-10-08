<?php

namespace Idei\Usim\Contracts;

use ArrayAccess;
use Idei\Usim\Contracts\Concerns\ArrayAccessibleDto;

/**
 * Result DTO for language synchronization operations.
 *
 * @phpstan-consistent-constructor
 *
 * @implements ArrayAccess<string, mixed>
 */
readonly class LangSyncResult implements ArrayAccess, SyncResultInterface
{
    use ArrayAccessibleDto;

    /**
     * @param  list<string>  $errors
     */
    final public function __construct(
        public int $languagesCreated = 0,
        public int $languagesUpdated = 0,
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
     * @return array{languages_created: int, languages_updated: int, skipped: bool, skip_reason: string, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'languages_created' => $this->languagesCreated,
            'languages_updated' => $this->languagesUpdated,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
            'errors' => $this->errors,
        ];
    }
}
