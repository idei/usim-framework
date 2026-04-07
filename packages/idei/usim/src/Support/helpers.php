<?php

use Idei\Usim\Services\Support\TranslationService;

if (!function_exists('t')) {
    /**
     * Resolve translated text with the following priority:
     *  1. DB-backed TranslationService (single source of truth).
     *  2. The key itself as last-resort fallback.
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        // 1. DB-backed TranslationService.
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

        // 2. Return the key itself.
        return $key;
    }
}
