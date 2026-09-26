<?php

namespace Idei\Usim\Support\Translation;

use Idei\Usim\Support\TranslationService;
use Throwable;

class TranslationResolver
{
    /**
     * Resolve translated text with the following priority:
     *  1. Laravel translator (__), honoring locale and placeholders.
     *  2. DB-backed TranslationService.
     *  3. The key itself as last-resort fallback.
     *
     * @param array<string, mixed> $params
     */
    public static function resolve(string $key, array $params = [], ?string $language = null): string
    {
        return self::resolveFromLaravel($key, $params, $language)
            ?? self::resolveFromDatabase($key, $params, $language)
            ?? $key;
    }

    /**
     * Attempt to resolve the translation through Laravel's translator.
     *
     * @param array<string, mixed> $params
     */
    private static function resolveFromLaravel(string $key, array $params, ?string $language): ?string
    {
        try {
            /** @var \Illuminate\Translation\Translator $translator */
            $translator = app('translator');
            $normalizedParams = self::normalizeParams($params);

            foreach (self::buildCandidates($key) as $candidate) {
                $hasTranslation = $language !== null
                    ? $translator->has($candidate, $language)
                    : $translator->has($candidate);

                if ($hasTranslation) {
                    $result = __($candidate, $normalizedParams, $language);
                    if (is_string($result)) {
                        return $result;
                    }
                }
            }
        } catch (Throwable) {
            // Translator unavailable — continue to next resolver.
        }

        return null;
    }

    /**
     * Attempt to resolve the translation through the database TranslationService.
     *
     * @param array<string, mixed> $params
     */
    private static function resolveFromDatabase(string $key, array $params, ?string $language): ?string
    {
        try {
            /** @var TranslationService $translationService */
            $translationService = app(TranslationService::class);
            $value = $translationService->safeGetValue($key, $params, $language);

            if ($value !== null && $value !== '' && $value !== $key) {
                return $value;
            }

            $translationService->registerMissingKey($key);
        } catch (Throwable) {
            // DB unavailable — continue to fallback.
        }

        return null;
    }

    /**
     * Generate candidate keys matching dotted syntax and nested lang file paths.
     * E.g.: 'screen.demo.events.main.menu_title' generates:
     *  - 'screen.demo.events.main.menu_title'
     *  - 'screen/demo.events.main.menu_title'
     *  - 'screen/demo/events.main.menu_title'
     *  - 'screen/demo/events/main.menu_title'
     *
     * @return list<string>
     */
    public static function buildCandidates(string $key): array
    {
        $candidates = [$key];
        $segments = explode('.', $key);
        $segmentCount = count($segments);

        for ($i = 2; $i <= $segmentCount; $i++) {
            $filePath = implode('/', array_slice($segments, 0, $i));
            $remaining = array_slice($segments, $i);

            $candidates[] = empty($remaining)
                ? "{$filePath}.value"
                : "{$filePath}." . implode('.', $remaining);
        }

        // Support package namespace fallback: usim.dialog.button.ok -> usim::dialog/button.ok
        if ($segments[0] === 'usim' && $segmentCount >= 4) {
            $pkgFilePath = implode('/', array_slice($segments, 1, 2));
            $pkgRemaining = array_slice($segments, 3);
            $candidates[] = 'usim::' . $pkgFilePath . '.' . implode('.', $pkgRemaining);
        }

        return $candidates;
    }

    /**
     * Normalize parameter values to scalars/strings expected by Laravel translator.
     *
     * @param array<string, mixed> $params
     * @return array<string, bool|float|int|string|null>
     */
    private static function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $paramKey => $value) {
            $normalized[$paramKey] = match (true) {
                is_scalar($value) || $value === null => $value,
                is_array($value) => json_encode($value) ?: '',
                is_object($value) && method_exists($value, '__toString') => (string) $value,
                is_resource($value) => get_resource_type($value),
                default => get_debug_type($value),
            };
        }

        return $normalized;
    }
}
