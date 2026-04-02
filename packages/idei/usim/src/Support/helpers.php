<?php

use Idei\Usim\Services\Support\TranslationService;

if (!function_exists('t')) {
    /**
     * Resolve translated text from DB keys with optional replacement parameters.
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        try {
            /** @var TranslationService $translationService */
            $translationService = app(TranslationService::class);
            $value = $translationService->safeGetValue($key, $params, $language);

            if ($value !== null && $value !== '') {
                return $value;
            }
        } catch (Throwable) {
            // Fall back to Laravel translator when translation tables are unavailable.
        }

        $fallback = __($key, $params);

        return is_string($fallback) && $fallback !== '' ? $fallback : $key;
    }
}
