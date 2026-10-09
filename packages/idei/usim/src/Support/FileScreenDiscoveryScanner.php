<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ScreenDiscoveryScannerInterface;
use Idei\Usim\Screen;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileScreenDiscoveryScanner implements ScreenDiscoveryScannerInterface
{
    /**
     * Scan the filesystem for instantiable Screen classes.
     *
     * @param  string|null  $path  Directory path to scan
     * @param  string|null  $namespace  Base namespace for discovered screens
     * @return list<class-string<Screen>>
     */
    public function scan(?string $path = null, ?string $namespace = null): array
    {
        $rawScreensPath = $path ?? config('usim.screens_path', app_path('UI/Screens'));
        $screensPath = is_string($rawScreensPath) ? $rawScreensPath : app_path('UI/Screens');

        if (! is_dir($screensPath)) {
            return [];
        }

        $rawNamespace = $namespace ?? config('usim.screens_namespace', 'App\\UI\\Screens');
        $screensNamespace = is_string($rawNamespace) ? $rawNamespace : 'App\\UI\\Screens';
        $screensNamespace = rtrim($screensNamespace, '\\');

        $finder = new Finder;
        $finder->files()->in($screensPath)->name('*.php');

        /** @var list<class-string<Screen>> $screens */
        $screens = [];

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file, $screensNamespace);

            if ($className !== null && $this->isValidScreenClass($className)) {
                $screens[] = $className;
            }
        }

        return array_values(array_unique($screens));
    }

    /**
     * Extract full class name from file based on PSR-4 directory structure.
     */
    protected function getClassNameFromFile(SplFileInfo $file, string $namespace): ?string
    {
        $relativePath = $file->getRelativePathname();

        return $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);
    }

    /**
     * Verify that the class exists, extends Screen, and is instantiable.
     *
     * @phpstan-assert-if-true class-string<Screen> $className
     */
    protected function isValidScreenClass(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        return $reflection->isSubclassOf(Screen::class) && ! $reflection->isAbstract();
    }
}

