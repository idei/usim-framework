<?php

namespace Idei\Usim\Navigation;

use Idei\Usim\Contracts\ScreenDiscoveryScannerInterface;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Support\Str;

/**
 * Automatic permission-aware MenuProvider for the built-in Workbench SidebarMenu.
 *
 * Discovers application Screens (preferring the cached `bootstrap/cache/usim_screens.php`
 * manifest with filesystem scanner fallback), filters out non-navigable technical screens
 * (layouts, modals, kiosks, auth/device/testing screens, shell bars, and screens with
 * `$showInMenu = false`), checks `$screen::checkAccess()['allowed']` for the active user,
 * places `Home` first with the SVG `home` icon, and organizes subdirectory screens cleanly.
 */
class AutoDiscoveredMenuProvider implements MenuProviderInterface
{
    /**
     * Sub-namespaces excluded from automatic sidebar discovery.
     *
     * @var list<string>
     */
    protected const EXCLUDED_SUBNAMESPACES = [
        'Auth',
        'Device',
        'Testing',
        'Concerns',
        'Modals',
    ];

    /**
     * Exact screen class basenames excluded from automatic sidebar discovery.
     *
     * @var list<string>
     */
    protected const EXCLUDED_BASENAMES = [
        'Menu',
        'MenuStub',
        'HomeStub',
        'SidebarMenu',
        'SidebarSubmenuPanel',
        'StatusBar',
        'ConfirmDialog',
    ];

    /**
     * Class basename suffixes excluded from automatic sidebar discovery (modals, dialogs, inspectors, stubs).
     *
     * @var list<string>
     */
    protected const EXCLUDED_SUFFIXES = [
        'Dialog',
        'Modal',
        'Inspector',
        'Stub',
    ];

    public function build(MenuBuilder $menu): void
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $screensNamespace = trim($usimConfig->screensNamespace, '\\');

        $candidates = $this->discoverCandidateScreens($usimConfig);
        $homeClass = $screensNamespace.'\\Home';

        /** @var list<class-string<Screen>> $rootScreens */
        $rootScreens = [];

        /** @var array<string, list<class-string<Screen>>> $groupedScreens */
        $groupedScreens = [];

        $hasHome = false;

        foreach ($candidates as $screenClass) {
            if (! $this->isNavigableForCurrentUser($screenClass, $screensNamespace)) {
                continue;
            }

            if ($screenClass === $homeClass) {
                $hasHome = true;

                continue;
            }

            $relative = str_starts_with($screenClass, $screensNamespace.'\\')
                ? substr($screenClass, strlen($screensNamespace) + 1)
                : class_basename($screenClass);

            $segments = explode('\\', $relative);
            if (\count($segments) === 1) {
                $rootScreens[] = $screenClass;
            } else {
                $group = $segments[0];
                $groupedScreens[$group][] = $screenClass;
            }
        }

        // 1. Home always comes first with the SVG 'home' icon
        if ($hasHome && is_subclass_of($homeClass, Screen::class)) {
            $item = $menu->screenShow($homeClass);
            $item->icon($this->resolveIconForScreen($homeClass, isHome: true));
        }

        // 2. Top-level screens sorted alphabetically by menu label
        usort($rootScreens, static fn (string $a, string $b): int => strcasecmp($a::getMenuLabel(), $b::getMenuLabel()));
        foreach ($rootScreens as $screenClass) {
            $item = $menu->screenShow($screenClass);
            $item->icon($this->resolveIconForScreen($screenClass));
        }

        // 3. Subdirectory screens: promote 1-item groups directly; group 2+ items into collapsible submenus
        ksort($groupedScreens);
        foreach ($groupedScreens as $groupName => $screensInGroup) {
            usort($screensInGroup, static fn (string $a, string $b): int => strcasecmp($a::getMenuLabel(), $b::getMenuLabel()));

            if (\count($screensInGroup) === 1) {
                $single = $screensInGroup[0];
                $item = $menu->screenShow($single);
                $item->icon($this->resolveIconForScreen($single));

                continue;
            }

            $groupLabel = Str::headline($groupName);
            $groupIcon = $this->resolveGroupIcon($groupName);

            $menu->submenu($groupLabel, function (MenuBuilder $sub) use ($screensInGroup): void {
                foreach ($screensInGroup as $screenClass) {
                    $item = $sub->screenShow($screenClass);
                    $item->icon($this->resolveIconForScreen($screenClass));
                }
            }, $groupIcon);
        }
    }

    /**
     * @return list<class-string<Screen>>
     */
    protected function discoverCandidateScreens(UsimConfig $usimConfig): array
    {
        $manifestPath = function_exists('app') ? app()->bootstrapPath('cache/usim_screens.php') : null;
        $screensNamespace = trim($usimConfig->screensNamespace, '\\');

        if (\is_string($manifestPath) && is_file($manifestPath)) {
            $manifest = require $manifestPath;
            if (\is_array($manifest) && $manifest !== []) {
                /** @var list<class-string<Screen>> $fromManifest */
                $fromManifest = [];
                foreach (array_keys($manifest) as $class) {
                    if (
                        \is_string($class)
                        && str_starts_with(ltrim($class, '\\'), $screensNamespace.'\\')
                        && class_exists($class)
                        && is_subclass_of($class, Screen::class)
                    ) {
                        $fromManifest[] = $class;
                    }
                }

                if ($fromManifest !== []) {
                    return $fromManifest;
                }
            }
        }

        if (app()->bound(ScreenDiscoveryScannerInterface::class)) {
            /** @var ScreenDiscoveryScannerInterface $scanner */
            $scanner = app(ScreenDiscoveryScannerInterface::class);

            return $scanner->scan($usimConfig->screensPath, $usimConfig->screensNamespace);
        }

        return [];
    }

    /**
     * @param  class-string<Screen>  $screenClass
     */
    protected function isNavigableForCurrentUser(string $screenClass, string $screensNamespace): bool
    {
        if (is_subclass_of($screenClass, AbstractLayout::class)) {
            return false;
        }

        if (! $screenClass::shouldShowInMenu()) {
            return false;
        }

        // Modals, kiosks, embedded shell screens, and inspectors declare $layout = null
        if ($screenClass::$layout === null) {
            return false;
        }

        $baseName = class_basename($screenClass);
        if (in_array($baseName, self::EXCLUDED_BASENAMES, true)) {
            return false;
        }

        foreach (self::EXCLUDED_SUFFIXES as $suffix) {
            if (str_ends_with($baseName, $suffix)) {
                return false;
            }
        }

        $relative = str_starts_with($screenClass, $screensNamespace.'\\')
            ? substr($screenClass, strlen($screensNamespace) + 1)
            : $baseName;

        $segments = explode('\\', $relative);
        foreach ($segments as $segment) {
            if (in_array($segment, self::EXCLUDED_SUBNAMESPACES, true)) {
                return false;
            }
        }

        try {
            $access = $screenClass::checkAccess();

            return ($access['allowed'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string<Screen>  $screenClass
     */
    protected function resolveIconForScreen(string $screenClass, bool $isHome = false): string
    {
        $configured = trim((string) $screenClass::getMenuIcon());

        // Prefer crisp SVG 'home' icon for Home even if legacy lang file has emoji '🏠' or is empty
        if ($isHome && ($configured === '' || $configured === '🏠')) {
            return 'home';
        }

        if ($configured !== '') {
            return $configured;
        }

        $lower = strtolower(class_basename($screenClass));

        return match (true) {
            str_contains($lower, 'home') => 'home',
            str_contains($lower, 'user') => 'users',
            str_contains($lower, 'role') || str_contains($lower, 'permission') => 'shield',
            str_contains($lower, 'translat') || str_contains($lower, 'lang') => 'message-square',
            str_contains($lower, 'setting') || str_contains($lower, 'config') => 'settings',
            str_contains($lower, 'dashboard') || str_contains($lower, 'catalog') => 'layout-grid',
            str_contains($lower, 'calendar') || str_contains($lower, 'schedule') => 'calendar',
            str_contains($lower, 'about') || str_contains($lower, 'info') => 'info',
            default => 'file',
        };
    }

    protected function resolveGroupIcon(string $groupName): string
    {
        $lower = strtolower($groupName);

        return match (true) {
            str_contains($lower, 'admin') => 'shield',
            str_contains($lower, 'demo') => 'sparkles',
            str_contains($lower, 'setting') => 'settings',
            default => 'folder',
        };
    }
}
