<?php

use Idei\Usim\Support\TranslationService;

if (!function_exists('t')) {
    /**
     * Resolve translated text with the following priority:
     *  1. Laravel translator (__), honoring locale and placeholders.
     *     - Tries the key as-is.
     *     - Tries the 3-level file-path variant: a.b.c.rest -> a/b/c.rest.
     *     - For keys starting with "usim.", also tries the package namespace
     *       usim::b/c.rest so the package lang files act as default fallback.
     *  2. DB-backed TranslationService.
     *  3. The key itself as last-resort fallback.
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        // 1. Laravel translator (__).
        try {
            /** @var \Illuminate\Translation\Translator $translator */
            $translator = app('translator');

            $candidates = [$key];
            $segments = explode('.', $key);

            // Support 3-level namespace files like lang/{locale}/a/b/c.php
            // while still allowing calls with dotted keys: a.b.c(.rest).
            if (count($segments) >= 3) {
                $fileKey = implode('/', array_slice($segments, 0, 3));
                $remaining = array_slice($segments, 3);

                if ($remaining === []) {
                    $candidates[] = $fileKey . '.value';
                } else {
                    $candidates[] = $fileKey . '.' . implode('.', $remaining);
                }
            }

            // For usim.* keys: also probe the package namespace (usim::)
            // so package lang files serve as default when the app has no override.
            // usim.dialog.button.ok -> usim::dialog/button.ok
            if ($segments[0] === 'usim' && count($segments) >= 4) {
                $pkgFileKey = implode('/', array_slice($segments, 1, 2));
                $pkgRemaining = array_slice($segments, 3);
                $candidates[] = 'usim::' . $pkgFileKey . '.' . implode('.', $pkgRemaining);
            }

            foreach ($candidates as $candidate) {
                $hasLaravelTranslation = $language !== null
                    ? $translator->has($candidate, $language)
                    : $translator->has($candidate);

                if ($hasLaravelTranslation) {
                    return __($candidate, $params, $language);
                }
            }
        } catch (Throwable) {
            // Translator unavailable — continue to DB fallback.
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
