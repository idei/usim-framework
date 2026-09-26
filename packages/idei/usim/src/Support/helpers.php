<?php

use Idei\Usim\Support\Translation\TranslationResolver;

if (!function_exists('t')) {
    /**
     * Resolve translated text with the following priority:
     *  1. Laravel translator (__), honoring locale and placeholders.
     *     - Tries the key as-is.
     *     - Tries nested file-path variants (e.g. a/b.rest, a/b/c.rest, etc.).
     *     - For keys starting with "usim.", also tries the package namespace
     *       usim::b/c.rest so the package lang files act as default fallback.
     *  2. DB-backed TranslationService.
     *  3. The key itself as last-resort fallback.
     *
     * @param array<string, mixed> $params
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        return TranslationResolver::resolve($key, $params, $language);
    }
}
