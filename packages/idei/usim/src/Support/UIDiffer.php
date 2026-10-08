<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\UIDifferInterface;

/**
 * UI Differ (Static Facade / Adapter)
 *
 * Forwards comparison calls to the UIDifferInterface singleton bound in the service container.
 */
class UIDiffer
{
    private static ?UIDifferInterface $fallbackInstance = null;

    protected static function getDiffer(): UIDifferInterface
    {
        if (function_exists('app') && app()->bound(UIDifferInterface::class)) {
            return app(UIDifferInterface::class);
        }

        if (self::$fallbackInstance === null) {
            self::$fallbackInstance = new ComponentDiffer;
        }

        return self::$fallbackInstance;
    }

    /**
     * Compare two UI component trees and return detected changes.
     *
     * @param  array<int|string, array<string, mixed>>  $oldUI
     * @param  array<int|string, array<string, mixed>>  $newUI
     * @return array<int|string, array<string, mixed>>
     */
    public static function compare(array $oldUI, array $newUI): array
    {
        return self::getDiffer()->compare($oldUI, $newUI);
    }
}
