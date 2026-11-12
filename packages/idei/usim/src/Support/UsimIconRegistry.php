<?php

namespace Idei\Usim\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Discovers published standard icons and user-defined custom icons
 * from configured public directories so the web renderer can resolve
 * semantic icon names without coupling backend JSON contracts to paths.
 */
class UsimIconRegistry
{
    /** @var list<string> Supported extensions ordered by preference */
    private const SUPPORTED_EXTENSIONS = ['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif', 'ico'];

    /**
     * Build the icon manifest map (`name => public URL path`).
     *
     * Higher-priority paths (earlier in `usim.icons.paths`) override lower-priority paths,
     * and explicit `usim.icons.aliases` take highest precedence.
     *
     * @return array<string, string>
     */
    public function getManifest(): array
    {
        $manifest = [];
        $paths = config('usim.icons.paths', [
            'images/icons',
            'vendor/idei/usim/icons',
        ]);

        if (is_array($paths)) {
            foreach ($paths as $relativeDir) {
                if (! is_string($relativeDir)) {
                    continue;
                }

                $normalizedDir = trim(str_replace('\\', '/', $relativeDir), '/');
                if ($normalizedDir === '') {
                    continue;
                }

                $this->scanDirectory($normalizedDir, $manifest);
            }
        }

        $aliases = config('usim.icons.aliases', []);
        if (is_array($aliases)) {
            foreach ($aliases as $name => $target) {
                if (! is_string($name) || ! is_string($target)) {
                    continue;
                }

                $cleanName = strtolower(trim($name));
                $cleanTarget = trim($target);
                if ($cleanName !== '' && $cleanTarget !== '') {
                    $manifest[$cleanName] = $cleanTarget;
                }
            }
        }

        return $manifest;
    }

    /**
     * Scan a public directory for icon files without overwriting higher-priority entries.
     *
     * @param  array<string, string>  $manifest
     */
    private function scanDirectory(string $normalizedDir, array &$manifest): void
    {
        $absoluteDir = public_path($normalizedDir);
        if (! is_dir($absoluteDir)) {
            return;
        }

        /** @var array<string, array<string, string>> $candidatesByKey */
        $candidatesByKey = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            $relativeFile = ltrim(
                str_replace('\\', '/', substr($file->getPathname(), strlen($absoluteDir))),
                '/'
            );

            $withoutExt = preg_replace('/\.[^.]+$/', '', $relativeFile);
            if (! is_string($withoutExt) || $withoutExt === '') {
                continue;
            }

            $key = strtolower($withoutExt);
            $publicUrl = '/'.$normalizedDir.'/'.$relativeFile;

            $candidatesByKey[$key][$extension] = $publicUrl;

            $filenameKey = strtolower($relativeFile);
            if (! isset($manifest[$filenameKey])) {
                $manifest[$filenameKey] = $publicUrl;
            }
        }

        foreach ($candidatesByKey as $key => $byExtension) {
            if (isset($manifest[$key])) {
                continue;
            }

            foreach (self::SUPPORTED_EXTENSIONS as $preferredExt) {
                if (isset($byExtension[$preferredExt])) {
                    $manifest[$key] = $byExtension[$preferredExt];
                    break;
                }
            }
        }
    }
}
