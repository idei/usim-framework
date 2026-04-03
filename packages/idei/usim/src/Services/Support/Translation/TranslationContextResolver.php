<?php

namespace Idei\Usim\Services\Support\Translation;

use Illuminate\Support\Str;

class TranslationContextResolver
{
    public function resolveCallerContext(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $caller = $this->findTranslationCallerFrame($trace);

        $file = $caller['file'] ?? null;
        $stackClass = $caller['class'] ?? null;
        $fileClass = $this->deriveClassFromFilePath($file);
        $class = $this->selectBestCallerClass($stackClass, $fileClass);

        return [
            'group' => $this->normalizeGroupFromClassOrFile($class, $file),
        ];
    }

    private function selectBestCallerClass(?string $stackClass, ?string $fileClass): ?string
    {
        if (!$stackClass) {
            return $fileClass;
        }

        if (!$fileClass) {
            return $stackClass;
        }

        $stackCount = count($this->splitClassSegments($stackClass));
        $fileCount = count($this->splitClassSegments($fileClass));

        return $fileCount > $stackCount ? $fileClass : $stackClass;
    }

    private function findTranslationCallerFrame(array $trace): array
    {
        $count = count($trace);

        for ($index = 0; $index < $count; $index++) {
            $function = $trace[$index]['function'] ?? null;

            if ($function === 't' && isset($trace[$index + 1])) {
                return $trace[$index + 1];
            }
        }

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;

            if ($class === 'Idei\\Usim\\Services\\Support\\TranslationService') {
                continue;
            }

            if (isset($frame['file']) || isset($frame['class'])) {
                return $frame;
            }
        }

        return [];
    }

    private function normalizeGroupFromClassOrFile(?string $class, ?string $file): string
    {
        if ($class) {
            $normalized = preg_replace('/^App\\\\/i', '', $class) ?? $class;
            $segments = $this->splitClassSegments($normalized);

            if ($segments !== []) {
                $className = array_pop($segments);
                $groupParts = array_map(static fn (string $segment): string => strtolower($segment), $segments);
                $groupParts[] = Str::snake($className);

                return implode('.', array_filter($groupParts, static fn (string $part): bool => $part !== ''));
            }
        }

        if ($file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $filename = Str::snake($filename);

            if ($filename !== '') {
                return $filename;
            }
        }

        return 'global';
    }

    private function splitClassSegments(string $class): array
    {
        if (str_contains($class, '\\')) {
            return array_values(array_filter(explode('\\', $class), static fn (string $segment): bool => $segment !== ''));
        }

        if (str_contains($class, '/')) {
            return array_values(array_filter(explode('/', $class), static fn (string $segment): bool => $segment !== ''));
        }

        if (str_contains($class, '_')) {
            return array_values(array_filter(explode('_', $class), static fn (string $segment): bool => $segment !== ''));
        }

        return [$class];
    }

    private function deriveClassFromFilePath(?string $file): ?string
    {
        if (!$file) {
            return null;
        }

        $normalizedPath = str_replace('\\\\', '/', $file);
        $appMarker = '/app/';
        $position = stripos($normalizedPath, $appMarker);

        if ($position === false) {
            return null;
        }

        $relative = substr($normalizedPath, $position + strlen($appMarker));
        if ($relative === false || $relative === '') {
            return null;
        }

        $withoutExtension = preg_replace('/\\.php$/i', '', $relative) ?? $relative;
        $segments = array_values(array_filter(explode('/', $withoutExtension), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        return 'App\\' . implode('\\', $segments);
    }
}
