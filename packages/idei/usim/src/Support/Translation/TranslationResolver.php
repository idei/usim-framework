<?php

namespace Idei\Usim\Support\Translation;

use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Support\UsimTranslator;

class TranslationResolver
{
    /**
     * Resolve translated text with the following priority:
     *  1. Laravel translator (__), honoring locale and placeholders.
     *  2. DB-backed TranslationService.
     *  3. The key itself as last-resort fallback.
     *
     * @param  array<string, mixed>  $params
     */
    public static function resolve(string $key, array $params = [], ?string $language = null): string
    {
        if (app()->bound(UsimTranslatorInterface::class)) {
            return app(UsimTranslatorInterface::class)->get($key, $params, $language);
        }

        return (new UsimTranslator)->get($key, $params, $language);
    }

    /**
     * Determine if a translation exists for the given key.
     */
    public static function has(string $key, ?string $language = null): bool
    {
        if (app()->bound(UsimTranslatorInterface::class)) {
            return app(UsimTranslatorInterface::class)->has($key, $language);
        }

        return (new UsimTranslator)->has($key, $language);
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
                : "{$filePath}.".implode('.', $remaining);
        }

        // Support package namespace fallback: usim.dialog.button.ok -> usim::dialog/button.ok
        if ($segments[0] === 'usim' && $segmentCount >= 4) {
            $pkgFilePath = implode('/', array_slice($segments, 1, 2));
            $pkgRemaining = array_slice($segments, 3);
            $candidates[] = 'usim::'.$pkgFilePath.'.'.implode('.', $pkgRemaining);
        }

        return $candidates;
    }
}
