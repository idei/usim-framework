<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for internationalization and translation resolution in USIM.
 */
interface UsimTranslatorInterface
{
    /**
     * Resolve translated string for the given key, replacing placeholders.
     *
     * @param  array<string, mixed>  $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string;

    /**
     * Determine if a translation exists for the given key.
     */
    public function has(string $key, ?string $locale = null): bool;

    /**
     * Get the current active locale.
     */
    public function getLocale(): string;

    /**
     * Set the current active locale.
     */
    public function setLocale(string $locale): void;
}
