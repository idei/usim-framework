<?php

namespace Idei\Usim\Support\Translation;

use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\Support\UsimTranslator;
use Illuminate\Support\Str;

class TranslationResolver
{
    /**
     * Cache of resolved class prefixes to avoid redundant parsing.
     *
     * @var array<string, string>
     */
    protected static array $prefixCache = [];

    /**
     * Explicit context class override (useful in unit testing).
     */
    protected static ?string $contextClass = null;

    /**
     * Classes to ignore when traversing debug_backtrace to locate the caller.
     *
     * @var array<class-string, bool>
     */
    protected static array $ignoredClasses = [
        self::class => true,
        UsimTranslator::class => true,
    ];

    /**
     * Resolve a translation key, automatically expanding relative keys starting with a dot ('.').
     */
    public static function resolveKey(string $key, ?string $callerClass = null): string
    {
        if (! str_starts_with($key, '.')) {
            return $key;
        }

        $class = $callerClass ?? self::$contextClass ?? self::detectCallerClass();

        if ($class === null) {
            return $key;
        }

        $prefix = self::resolveClassPrefix($class);

        return $prefix !== null ? $prefix.$key : $key;
    }

    /**
     * Set explicit caller context class override.
     */
    public static function setContextClass(?string $className): void
    {
        self::$contextClass = $className;
    }

    /**
     * Clear memoized prefixes and context.
     */
    public static function clearCache(): void
    {
        self::$prefixCache = [];
        self::$contextClass = null;
    }

    /**
     * Detect the calling class from the call stack.
     */
    public static function detectCallerClass(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($trace as $frame) {
            if (! isset($frame['class'])) {
                continue;
            }

            $className = $frame['class'];

            if (isset(self::$ignoredClasses[$className])) {
                continue;
            }

            if (str_starts_with($className, 'Illuminate\\')) {
                continue;
            }

            if (str_starts_with($className, 'Idei\\Usim\\Support\\Translation\\')) {
                continue;
            }

            return $className;
        }

        return null;
    }

    /**
     * Resolve the translation prefix for a given class name.
     */
    public static function resolveClassPrefix(string $class): ?string
    {
        if (isset(self::$prefixCache[$class])) {
            return self::$prefixCache[$class];
        }

        $rawScreens = config('usim.screens_namespace');
        $screensNs = is_string($rawScreens) && $rawScreens !== '' ? trim($rawScreens, '\\') : 'App\\UI\\Screens';

        $rawRequests = config('usim.requests_namespace');
        $requestsNs = is_string($rawRequests) && $rawRequests !== '' ? trim($rawRequests, '\\') : 'App\\UI\\Requests';
        $normalized = trim($class, '\\');

        $prefix = null;

        if ($screensNs !== '' && (str_starts_with($normalized, $screensNs.'\\') || $normalized === $screensNs)) {
            $sub = substr($normalized, strlen($screensNs));
            $parts = array_values(array_filter(explode('\\', $sub)));
            $snake = array_map(fn (string $p): string => Str::snake($p), $parts);
            $prefix = 'screens'.($snake !== [] ? '.'.implode('.', $snake) : '');
        } elseif ($requestsNs !== '' && (str_starts_with($normalized, $requestsNs.'\\') || $normalized === $requestsNs)) {
            $sub = substr($normalized, strlen($requestsNs));
            $parts = array_values(array_filter(explode('\\', $sub)));
            $snake = array_map(fn (string $p): string => Str::snake($p), $parts);
            $prefix = 'requests'.($snake !== [] ? '.'.implode('.', $snake) : '');
        } elseif (is_subclass_of($class, Screen::class)) {
            if (str_contains($normalized, 'Screens\\')) {
                $sub = Str::after($normalized, 'Screens\\');
                $parts = array_values(array_filter(explode('\\', $sub)));
                $snake = array_map(fn (string $p): string => Str::snake($p), $parts);
                $prefix = 'screens.'.implode('.', $snake);
            } else {
                $prefix = 'screens.'.Str::snake(class_basename($normalized));
            }
        } elseif (is_subclass_of($class, UIRequest::class)) {
            if (str_contains($normalized, 'Requests\\')) {
                $sub = Str::after($normalized, 'Requests\\');
                $parts = array_values(array_filter(explode('\\', $sub)));
                $snake = array_map(fn (string $p): string => Str::snake($p), $parts);
                $prefix = 'requests.'.implode('.', $snake);
            } else {
                $prefix = 'requests.'.Str::snake(class_basename($normalized));
            }
        } elseif (str_starts_with($normalized, 'App\\UI\\')) {
            $sub = substr($normalized, strlen('App\\UI\\'));
            $parts = array_values(array_filter(explode('\\', $sub)));
            $snake = array_map(fn (string $p): string => Str::snake($p), $parts);
            $prefix = implode('.', $snake);
        }

        if ($prefix !== null) {
            self::$prefixCache[$class] = $prefix;
        }

        return $prefix;
    }

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
        $key = self::resolveKey($key);

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
        $key = self::resolveKey($key);

        if (app()->bound(UsimTranslatorInterface::class)) {
            return app(UsimTranslatorInterface::class)->has($key, $language);
        }

        return (new UsimTranslator)->has($key, $language);
    }

    /**
     * Generate candidate keys matching dotted syntax and nested lang file paths.
     * E.g.: 'screens.demo.events.main.menu_title' generates:
     *  - 'screens.demo.events.main.menu_title'
     *  - 'screens/demo.events.main.menu_title'
     *  - 'screens/demo/events.main.menu_title'
     *  - 'screens/demo/events/main.menu_title'
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

        if (str_contains($key, '_request.')) {
            $candidateWithoutSuffix = str_replace('_request.', '.', $key);
            $candidates[] = $candidateWithoutSuffix;
            foreach (self::buildCandidates($candidateWithoutSuffix) as $c) {
                if (! in_array($c, $candidates, true)) {
                    $candidates[] = $c;
                }
            }
        }

        return $candidates;
    }
}
