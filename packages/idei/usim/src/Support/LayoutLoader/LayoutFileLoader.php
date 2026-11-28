<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Resolves, parses, and caches declarative `.layout.yaml` files from disk.
 */
final class LayoutFileLoader
{
    /**
     * In-memory cache of parsed YAML layout files keyed by "realPath:mtime".
     *
     * @var array<string, array<string, mixed>>
     */
    private array $parsedCache = [];

    /**
     * @return array<string, mixed>
     */
    public function loadFile(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        return $this->loadFileWithMeta($path, $callingContext, $baseDir)['definition'];
    }

    /**
     * @return array{definition: array<string, mixed>, resolvedPath: string}
     */
    public function loadFileWithMeta(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        $resolvedPath = $this->resolvePath($path, $callingContext, $baseDir);
        $mtime = @filemtime($resolvedPath) ?: 0;
        $cacheKey = "{$resolvedPath}:{$mtime}";

        if (isset($this->parsedCache[$cacheKey])) {
            return [
                'definition' => $this->parsedCache[$cacheKey],
                'resolvedPath' => $resolvedPath,
            ];
        }

        $parsed = Yaml::parseFile($resolvedPath);
        if (! is_array($parsed)) {
            throw new InvalidArgumentException("Layout file [{$resolvedPath}] must contain a valid YAML mapping.");
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $parsed;
        $this->parsedCache[$cacheKey] = $normalized;

        return [
            'definition' => $normalized,
            'resolvedPath' => $resolvedPath,
        ];
    }

    public function hasDefaultLayout(?string $callingContext): bool
    {
        return $this->findDefaultLayoutFile($callingContext) !== null;
    }

    public function resolvePath(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): string
    {
        $contextDir = $this->resolveContextDirectory($callingContext);

        if ($path === null || trim($path) === '') {
            return $this->resolveDefaultPath($callingContext, $contextDir);
        }

        $candidates = $this->buildCandidateFilenames(trim($path));

        if ($baseDir !== null && $baseDir !== '') {
            $found = $this->findInDirectory($baseDir, $candidates);
            if ($found !== null) {
                return $found;
            }
        }

        if ($contextDir !== null) {
            $found = $this->findInDirectory($contextDir, $candidates);
            if ($found !== null) {
                return $found;
            }
        }

        $found = $this->findInBaseOrAbsolute($candidates);
        if ($found !== null) {
            return $found;
        }

        throw new InvalidArgumentException("Layout file [{$path}] not found.");
    }

    private function resolveDefaultPath(?string $callingContext, ?string $contextDir): string
    {
        if ($callingContext === null || $contextDir === null) {
            throw new InvalidArgumentException('Cannot auto-resolve layout file without a valid Screen context.');
        }

        $defaultFile = $this->findDefaultLayoutFile($callingContext);
        if ($defaultFile !== null) {
            return $defaultFile;
        }

        $expectedFile = $contextDir.'/'.Str::kebab(class_basename($callingContext)).'.layout.yaml';

        throw new InvalidArgumentException("Default layout file [{$expectedFile}] not found for [{$callingContext}].");
    }

    private function findDefaultLayoutFile(?string $callingContext): ?string
    {
        if ($callingContext === null || ! class_exists($callingContext)) {
            return null;
        }

        $cursor = $callingContext;
        while ($cursor !== null) {
            if ($cursor === Screen::class || $cursor === AbstractLayout::class) {
                break;
            }

            $contextDir = $this->resolveContextDirectory($cursor);
            if ($contextDir !== null) {
                $defaultFile = $contextDir.'/'.Str::kebab(class_basename($cursor)).'.layout.yaml';
                if (is_file($defaultFile)) {
                    return (string) realpath($defaultFile);
                }
            }

            $parent = get_parent_class($cursor);
            $cursor = is_string($parent) ? $parent : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildCandidateFilenames(string $trimmedPath): array
    {
        $candidates = [$trimmedPath];

        if (! str_ends_with($trimmedPath, '.yaml') && ! str_ends_with($trimmedPath, '.yml')) {
            $candidates[] = "{$trimmedPath}.layout.yaml";
            $candidates[] = "{$trimmedPath}.yaml";
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findInDirectory(string $directory, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $resolved = $directory.'/'.ltrim($candidate, '/');
            if (is_file($resolved)) {
                return (string) realpath($resolved);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findInBaseOrAbsolute(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return (string) realpath($candidate);
            }

            if (function_exists('base_path')) {
                $fromBase = base_path($candidate);
                if (is_file($fromBase)) {
                    return (string) realpath($fromBase);
                }
            }
        }

        return null;
    }

    private function resolveContextDirectory(?string $callingContext): ?string
    {
        if ($callingContext === null || ! class_exists($callingContext)) {
            return null;
        }

        $reflection = new ReflectionClass($callingContext);
        $fileName = $reflection->getFileName();

        return is_string($fileName) ? dirname($fileName) : null;
    }
}
