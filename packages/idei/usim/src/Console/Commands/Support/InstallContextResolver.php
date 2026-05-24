<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Support\Str;

class InstallContextResolver
{
    /**
     * @return array{screensNamespace:string,screensPath:string,componentsNamespace:string,componentsPath:string}
     */
    public function resolveNamespaces(): array
    {
        $screensNamespace = config('usim.screens_namespace', config('ui-services.screens_namespace', 'App\\UI\\Screens'));
        $screensPath = config('usim.screens_path', config('ui-services.screens_path', app_path('UI/Screens')));

        $normalizedScreensNamespace = is_string($screensNamespace) && $screensNamespace !== ''
            ? $screensNamespace
            : 'App\\UI\\Screens';
        $normalizedScreensPath = is_string($screensPath) && $screensPath !== ''
            ? $screensPath
            : app_path('UI/Screens');

        return [
            'screensNamespace' => $normalizedScreensNamespace,
            'screensPath' => $normalizedScreensPath,
            'componentsNamespace' => Str::beforeLast($normalizedScreensNamespace, '\\Screens') . '\\Components',
            'componentsPath' => Str::beforeLast($normalizedScreensPath, '/Screens') . '/Components',
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    public function buildScaffoldingContext(
        string $stubsBasePath,
        array $namespaces,
        string $userModelImport,
        string $userModelClass,
        bool $force
    ): array {
        return [
            'stubsBasePath' => $stubsBasePath,
            'screensNamespace' => (string) ($namespaces['screensNamespace'] ?? 'App\\UI\\Screens'),
            'screensPath' => (string) ($namespaces['screensPath'] ?? app_path('UI/Screens')),
            'componentsNamespace' => (string) ($namespaces['componentsNamespace'] ?? 'App\\UI\\Components'),
            'componentsPath' => (string) ($namespaces['componentsPath'] ?? app_path('UI/Components')),
            'userModelImport' => $userModelImport,
            'userModelClass' => $userModelClass,
            'force' => $force,
        ];
    }

    public function stubsPath(string $path = ''): string
    {
        return dirname(__DIR__, 4) . '/stubs/' . ltrim($path, '/');
    }
}
