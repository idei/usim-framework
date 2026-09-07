<?php
// @usim: feature="admin", type="service"
namespace App\Services\Units;

class UnitSyncResult
{
    /**
     * @param array<int, string> $generatedTranslationFiles
     * @param array<int, string> $errors
     */
    public function __construct(
        public readonly bool $skipped = false,
        public readonly string $skipReason = '',
        public readonly int $deletedCount = 0,
        public readonly int $upsertedCount = 0,
        public readonly int $hierarchyUpdatedCount = 0,
        public readonly array $generatedTranslationFiles = [],
        public readonly array $errors = []
    ) {
    }

    /**
     * Create a result representing a skipped operation.
     */
    public static function skipped(string $reason): self
    {
        return new self(
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

