<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Support\Str;

class InstallContextResolver
{
    /**
     * @return array{screensNamespace:string,screensPath:string,componentsNamespace:string,componentsPath:string,layoutsNamespace:string,layoutsPath:string}
     */
    public function resolveNamespaces(): array
    {
        $screensNamespace = config('usim.screens_namespace', 'App\\UI\\Screens');
        $screensPath = config('usim.screens_path', app_path('UI/Screens'));

        $normalizedScreensNamespace = \is_string($screensNamespace) && $screensNamespace !== ''
            ? $screensNamespace
            : 'App\\UI\\Screens';
        $normalizedScreensPath = \is_string($screensPath) && $screensPath !== ''
            ? $screensPath
            : app_path('UI/Screens');

        $normalizedScreensPath = str_replace('\\', '/', $normalizedScreensPath);

        return [
            'screensNamespace' => $normalizedScreensNamespace,
            'screensPath' => $normalizedScreensPath,
            'componentsNamespace' => Str::beforeLast($normalizedScreensNamespace, '\\Screens').'\\Components',
            'componentsPath' => Str::beforeLast($normalizedScreensPath, '/Screens').'/Components',
            'layoutsNamespace' => Str::beforeLast($normalizedScreensNamespace, '\\Screens').'\\Layouts',
            'layoutsPath' => Str::beforeLast($normalizedScreensPath, '/Screens').'/Layouts',
        ];
    }

    /**
     * @param  array<string, string>  $namespaces
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
            'layoutsNamespace' => (string) ($namespaces['layoutsNamespace'] ?? 'App\\UI\\Layouts'),
            'layoutsPath' => (string) ($namespaces['layoutsPath'] ?? app_path('UI/Layouts')),
            'userModelImport' => $userModelImport,
            'userModelClass' => $userModelClass,
            'force' => $force,
        ];
    }

    public function stubsPath(string $path = ''): string
    {
        return dirname(__DIR__, 4).'/stubs/'.ltrim($path, '/\\');
    }
}
