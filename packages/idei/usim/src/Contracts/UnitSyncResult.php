<?php

namespace Idei\Usim\Contracts;

/**
 * Result DTO for organizational unit synchronization operations.
 *
 * @phpstan-consistent-constructor
 */
readonly class UnitSyncResult
{
    /**
     * @param array<int, string> $generatedTranslationFiles
     * @param array<int, string> $errors
     */
    final public function __construct(
        public bool $skipped = false,
        public string $skipReason = '',
        public int $deletedCount = 0,
        public int $upsertedCount = 0,
        public int $hierarchyUpdatedCount = 0,
        public array $generatedTranslationFiles = [],
        public array $errors = []
    ) {
    }

    /**
     * Create a result representing a skipped operation.
     */
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

    public function isSuccess(): bool
    {
        return !$this->skipped && empty($this->errors);
    }
}

