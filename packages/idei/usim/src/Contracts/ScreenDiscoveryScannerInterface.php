<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\Screen;

interface ScreenDiscoveryScannerInterface
{
    /**
     * Scan the filesystem or source directory for instantiable Screen classes.
     *
     * @param  string|null  $path  Directory path to scan (defaults to config('usim.screens_path', app_path('UI/Screens')))
     * @param  string|null  $namespace  Base namespace for discovered screens (defaults to config('usim.screens_namespace', 'App\UI\Screens'))
     * @return list<class-string<Screen>>
     */
    public function scan(?string $path = null, ?string $namespace = null): array;
}

