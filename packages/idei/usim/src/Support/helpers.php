<?php

use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;

if (! function_exists('t')) {
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
     * @param  array<string, mixed>  $params
     */
    function t(string $key, array $params = [], ?string $language = null): string
    {
        return app(UsimTranslatorInterface::class)->get($key, $params, $language);
    }
}

if (! function_exists('testScreen')) {
    /**
     * Create an in-memory test harness for a Screen.
     *
     * @template T of \Idei\Usim\Screen
     *
     * @param  class-string<T>|T  $screen
     * @param  array<string, mixed>  $storage
     * @param  array<string, mixed>  $query
     * @return ScreenTestHarness<T>
     */
    function testScreen(string|Screen $screen, array $storage = [], array $query = []): ScreenTestHarness
    {
        /** @var ScreenTestHarness<T> $harness */
        $harness = ScreenTestHarness::for($screen, $storage, $query);

        return $harness;
    }
}
