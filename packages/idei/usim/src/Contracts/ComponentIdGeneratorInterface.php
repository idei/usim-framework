<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for centralized UI component ID generation and context tracking.
 */
interface ComponentIdGeneratorInterface
{
    /**
     * Push a context class onto the execution stack.
     */
    public function pushCurrentContext(string $context): void;

    /**
     * Pop the current context from the execution stack.
     */
    public function popCurrentContext(): ?string;

    /**
     * Get the active context from the execution stack.
     */
    public function getCurrentContext(): ?string;

    /**
     * Generate an auto-incremented unique ID for a component within a context.
     */
    public function generate(string $context): int;

    /**
     * Generate a deterministic unique ID for a component based on its name and context.
     */
    public function generateFromName(string $context, string $name): int;

    /**
     * Get debug context information (offset, current count, etc.).
     *
     * @return array<string, mixed>
     */
    public function getContextInfo(string $context): array;

    /**
     * Reserve an existing ID for a context to avoid collisions upon hydration.
     */
    public function reserveContextId(string $context, int $id): void;

    /**
     * Resolve the context class name associated with a component ID.
     */
    public function getContextFromId(int $id): ?string;

    /**
     * Get the ID offset assigned to a context.
     */
    public function getContextOffset(string $context): int;

    /**
     * Reset all generator state and counters (e.g. between requests).
     */
    public function reset(): void;
}
