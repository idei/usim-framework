<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for all USIM synchronization operation results.
 */
interface SyncResultInterface
{
    /**
     * Whether the synchronization was successful with no fatal errors.
     */
    public function isSuccess(): bool;

    /**
     * Whether the synchronization was skipped.
     */
    public function isSkipped(): bool;

    /**
     * Reason why the synchronization was skipped, or null if not skipped.
     */
    public function getSkipReason(): ?string;

    /**
     * List of warnings or error messages encountered during sync.
     *
     * @return list<string>
     */
    public function getErrors(): array;

    /**
     * Export synchronization statistics as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
