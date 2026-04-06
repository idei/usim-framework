<?php

use Idei\Usim\Services\Support\Translation\TranslationAutoRegistrar;
use Idei\Usim\Services\Support\TranslationService;

if (!function_exists('t')) {
    /**
     * Resolve translated text with the following priority:
     *  1. Static lang/ files (fast, no DB hit) — populated via "Publish to lang".
     *  2. DB-backed TranslationService (live, editable).
     *  3. The key itself as last-resort fallback.
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        // 1. Static lang/ files — use the explicit locale or the app locale.
        $locale = $language ?? app()->getLocale();
        if (app('translator')->has($key, $locale, false)) {
            $static = __($key, $params, $locale);
            if (is_string($static) && $static !== '') {
                return $static;
            }
        }

        // 1b. If the input is human-readable text, derive the same candidate key
        // used by the DB auto-registration flow and try lang/ with that key too.
        try {
            /** @var TranslationAutoRegistrar $autoRegistrar */
            $autoRegistrar = app(TranslationAutoRegistrar::class);
            $derivedKey = $autoRegistrar->deriveKeyCandidate($key);

            if ($derivedKey !== $key && app('translator')->has($derivedKey, $locale, false)) {
                $static = __($derivedKey, $params, $locale);
                if (is_string($static) && $static !== '') {
                    return $static;
                }
            }
        } catch (Throwable) {
            // Continue to DB-backed translation lookup.
        }

        // 2. DB-backed TranslationService.
        try {
            /** @var TranslationService $translationService */
            $translationService = app(TranslationService::class);
            $value = $translationService->safeGetValue($key, $params, $language);

            if ($value !== null && $value !== '') {
                return $value;
            }
        } catch (Throwable) {
            // DB unavailable — continue to key fallback.
        }

        // 3. Return the key itself.
        return $key;
    }
}
