<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Components\Container;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use RuntimeException;

/**
 * Navigation, slots, and client meta-actions logic for Screen.
 *
 * @mixin Screen
 */
trait HandlesNavigation
{
    abstract public function getLayout(): ?AbstractLayout;

    abstract public function getContainer(): ?Container;

    abstract protected function uiChanges(): UIChangesCollector;
    /**
     * Show a target screen inside a specific slot container or layout slot.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Slot name (e.g., 'main', 'content', 'sidebar')
     * @param  array<int|string, mixed>  $params  Parameters passed to target's buildBaseUI
     * @param  bool  $updateBrowserUrl  Whether to update browser location history for main content navigation
     * @param  bool  $force  Whether to force reload the slot and clear cached screen snapshot even if target matches current
     */
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true, bool $force = false): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass === null || ! class_exists($targetClass) || ! is_subclass_of($targetClass, Screen::class)) {
            throw new RuntimeException("Target screen [{$screenClass}] is not a valid Screen instance.");
        }

        $access = $targetClass::checkAccess();
        if (! $access['allowed']) {
            $redirectUrl = $access['params']['url'] ?? null;
            if (($access['action'] ?? null) === 'redirect' && is_string($redirectUrl) && $redirectUrl !== '') {
                $this->redirect($redirectUrl);
            } else {
                $code = $access['params']['code'] ?? 403;
                $message = $access['params']['message'] ?? 'Unauthorized';
                $this->abort(is_int($code) ? $code : 403, is_string($message) ? $message : 'Unauthorized');
            }

            return false;
        }

        $effectiveSlot = $slot ?? $targetClass::getDefaultSlot();
        $isMainSlot = in_array($effectiveSlot, ['main', 'content', 'center'], true);
        $routeSlug = Screen::resolveScreenSlug($targetClass);

        // If a full-layout transition to this exact screen already occurred in this request cycle
        // (e.g. both Login::onLoggedUser and Menu::onLoggedUser reacting to logged_user), do not overwrite clear_container='main'.
        if ($isMainSlot) {
            $existingChanges = $this->uiChanges()->all();
            $existingNav = isset($existingChanges['navigate']) && is_array($existingChanges['navigate'])
                ? $existingChanges['navigate']
                : [];
            if (($existingChanges['clear_container'] ?? null) === 'main' && ($existingNav['route'] ?? null) === $routeSlug) {
                return true;
            }
        }

        // Automatic layout transition when navigating the main slot across different layouts (including null <-> Layout)
        if ($isMainSlot && UIStateManager::hasClientActiveLayout()) {
            $mountedLayout = UIStateManager::getClientActiveLayout();
            $targetLayout = $targetClass::getLayoutClass();

            if ($mountedLayout !== $targetLayout) {
                $routePath = $targetClass::getRoutePath();

                $queryParams = Screen::normalizeParamsForUrl($params);
                $navigateUrl = ! empty($queryParams)
                    ? $routePath.'?'.http_build_query($queryParams)
                    : $routePath;

                $this->uiChanges()->add([
                    'clear_container' => 'main',
                ]);

                AbstractLayout::setCurrent(null);
                Screen::make($targetClass)->clearCachedScreenSnapshot();
                if ($targetLayout !== null && class_exists($targetLayout)) {
                    Screen::make($targetLayout)->clearCachedScreenSnapshot();
                }

                $targetScreen = Screen::make($targetClass);
                $targetScreen->render(
                    incomingStorage: $this->uiChanges()->getStorage(),
                    queryParams: Screen::getCurrentQueryParams(),
                    parent: 'main',
                    shouldReset: true,
                    buildParams: $params
                );

                UIStateManager::setClientActiveLayout($targetLayout);
                UIStateManager::setClientCurrentScreen($routePath, (string) $targetClass);

                if ($updateBrowserUrl) {
                    $this->uiChanges()->add([
                        'navigate' => [
                            'url' => $navigateUrl,
                            'route' => $routeSlug,
                            'title' => $targetClass::getMenuLabel(),
                            'slot' => 'main',
                            'slot_container_id' => 'main',
                        ],
                    ]);
                }

                return true;
            }
        }

        $layout = $this->getLayout();
        $currentScreen = $layout?->getActiveScreen($effectiveSlot);
        if ($currentScreen === null && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            $currentScreen = UIStateManager::getClientCurrentScreenClass();
        }

        // If the target screen is already active in the slot, no need to reload unless forced or passing params
        if (! $force && empty($params) && $currentScreen !== null) {
            $currentClass = class_exists($currentScreen) ? $currentScreen : Screen::resolveScreenClassFromSlug($currentScreen);
            if ($currentClass === $targetClass) {
                return true;
            }
        }

        if ($force || ! empty($params)) {
            Screen::make($targetClass)->clearCachedScreenSnapshot();
        }

        $routePath = $targetClass::getRoutePath();
        $routeSlug = Screen::resolveScreenSlug($targetClass);

        // 1. Locate slot container from layout instance if available
        if ($layout !== null) {
            $layout->setActiveScreen($effectiveSlot, $targetClass);
            if ($layout->getSlot($effectiveSlot) !== null) {
                return $layout->showInto($targetClass, $effectiveSlot, $params, $updateBrowserUrl, $force);
            }
        }

        // 2. Fallback: Search in the current screen's component tree
        $targetContainer = null;
        $rootContainer = $this->getContainer();
        if ($rootContainer !== null) {
            $found = $rootContainer->findByName($effectiveSlot);
            if ($found instanceof Container) {
                $targetContainer = $found;
            } elseif ($effectiveSlot === 'main' || $effectiveSlot === 'content') {
                $foundContent = $rootContainer->findByName('content_container');
                if ($foundContent instanceof Container) {
                    $targetContainer = $foundContent;
                }
            }
        }

        // Only clear and embed locally if target container is physically present in this screen's tree
        if ($targetContainer instanceof Container) {
            $targetContainer->clear();
            Screen::embedInto($targetClass, $targetContainer, $params);
        }

        // Update browser URL and instruct frontend to load screen into slot
        if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            $queryParams = Screen::normalizeParamsForUrl($params);
            $navigateUrl = ! empty($queryParams)
                ? $routePath.'?'.http_build_query($queryParams)
                : $routePath;

            UIStateManager::setClientCurrentScreen($routePath, (string) $targetClass);
            $this->uiChanges()->add([
                'navigate' => [
                    'url' => $navigateUrl,
                    'route' => $routeSlug,
                    'title' => $targetClass::getMenuLabel(),
                    'slot' => $targetContainer?->getName() ?? ($effectiveSlot === 'main' ? 'content_container' : $effectiveSlot),
                ],
            ]);
        }

        return true;
    }

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Optional slot override
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool
    {
        return $this->showInto($screenClass, $slot, $params);
    }

    /**
     * Navigate to a target screen inside the active layout shell.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     * @param  string|null  $slot  Optional slot override (defaults to target's $defaultSlot)
     */
    public function navigate(string $screenClass, array $params = [], ?string $slot = null): bool
    {
        return $this->show($screenClass, $slot, $params);
    }

    /**
     * Open (expand) an auxiliary layout slot (e.g. 'left_panel', 'right_panel').
     * If this Screen declares a portal container for that slot and the slot was temporarily
     * replaced by another screen (e.g. SidebarSubmenuPanel) or cleared, re-mounts the portal automatically.
     */
    public function openSlot(string $slot = 'right_panel', ?string $size = null): bool
    {
        $layout = $this->getLayout();
        if ($layout === null) {
            return false;
        }

        $target = $layout->resolveProjectionSlot($slot);
        if ($target === null) {
            return false;
        }

        $canonical = $target['canonical'];
        $portal = $this->slotContainer($slot);

        if ($portal instanceof Container) {
            $meta = $portal->getPortalMeta();
            $currentOwner = $layout->getSlotOwner($canonical);
            $isAlreadyOwnedByThisPortal = $currentOwner !== null
                && $currentOwner['mode'] === 'portal'
                && $currentOwner['owner'] === static::class
                && ($currentOwner['portal_container_id'] ?? null) === $portal->getId();

            if (! $isAlreadyOwnedByThisPortal || $portal->isPortalUnmounted()) {
                $portal->portalUnmounted(false)
                    ->setPortalSlotName($canonical)
                    ->portalTargetId($target['container_id']);

                if (! $target['external'] && $target['container'] instanceof Container) {
                    $target['container']->clear();
                }

                $this->uiChanges()->add([
                    'clear_container' => $target['container_id'],
                ]);
                $this->uiChanges()->add($portal->toJson());

                $layout->setSlotOwner($canonical, [
                    'mode' => 'portal',
                    'owner' => static::class,
                    'portal_container_id' => $portal->getId(),
                    'persist_on_navigate' => $meta['persist_on_navigate'] ?? false,
                ]);

                if ($canonical === 'status_bar_center') {
                    $layout->setStatusBarMessageVisible(false);
                }
            }

            $effectiveSize = $size ?? ($meta['size'] ?? null);
            if ($effectiveSize !== null) {
                $layout->setSlotSize($canonical, $effectiveSize);
            }
        } elseif ($size !== null) {
            $layout->setSlotSize($canonical, $size);
        }

        $expanded = $layout->expandSlot($canonical);
        $layout->persistLayoutState();

        return $expanded || $portal instanceof Container;
    }

    /**
     * Close an auxiliary layout slot (e.g. 'right_panel', 'left_panel').
     *
     * - When owned by a portal of the current Screen (and $clear is not explicitly true):
     *   collapses the Split pane while keeping the portal components alive so state is preserved
     *   and can be updated & re-opened via `openSlot()`.
     * - When owned by an external sub-Screen (mounted via `showInto`) or when `$clear = true`:
     *   clears the slot DOM and collapses its Split pane.
     */
    public function closeSlot(string $slot = 'right_panel', bool $collapse = true, ?bool $clear = null): bool
    {
        $layout = $this->getLayout();
        if ($layout === null) {
            return false;
        }

        $target = $layout->resolveProjectionSlot($slot);
        if ($target === null) {
            return false;
        }

        $canonical = $target['canonical'];
        $owner = $layout->getSlotOwner($canonical);
        $isOwnPortal = $owner !== null
            && $owner['mode'] === 'portal'
            && $owner['owner'] === static::class;

        $shouldClear = $clear ?? (! $isOwnPortal);

        if (! $shouldClear) {
            $collapsed = $collapse ? $layout->collapseSlot($canonical) : true;
            $layout->persistLayoutState();

            return $collapsed;
        }

        return $layout->clearSlot($canonical, $collapse);
    }

    /**
     * Toggle an auxiliary layout slot open or closed.
     */
    public function toggleSlot(string $slot = 'right_panel'): bool
    {
        return $this->isSlotOpen($slot)
            ? $this->closeSlot($slot)
            : $this->openSlot($slot);
    }

    /**
     * Determine whether an auxiliary layout slot is currently open (expanded).
     */
    public function isSlotOpen(string $slot = 'right_panel'): bool
    {
        $layout = $this->getLayout();
        if ($layout === null) {
            return false;
        }

        return $layout->isSlotExpanded($slot);
    }

    /**
     * Retrieve the portal Container declared by this Screen for a target layout slot, if any.
     */
    public function slotContainer(string $slot): ?Container
    {
        $rootContainer = $this->getContainer();
        if ($rootContainer === null) {
            return null;
        }

        $normalized = trim($slot);
        $layout = $this->getLayout();
        $resolvedTarget = $layout?->resolveProjectionSlot($normalized);
        $canonical = $resolvedTarget['canonical']
            ?? ($layout?->resolveCanonicalSlotName($normalized) ?? $normalized);

        foreach ($rootContainer->collectPortalContainers() as $portal) {
            $portalSlot = $portal->getPortalSlot();
            if ($portalSlot === null) {
                continue;
            }

            $portalCanonical = $layout?->resolveProjectionSlot($portalSlot)['canonical']
                ?? ($layout?->resolveCanonicalSlotName($portalSlot) ?? $portalSlot);

            if ($portalSlot === $normalized || $portalCanonical === $canonical) {
                return $portal;
            }
        }

        return null;
    }

    /**
     * Send a toast notification to the frontend.
     */
    public function toast(
        string $message,
        string $type = 'info',
        int $duration = 4000,
        string $openEffect = 'fade',
        string $showEffect = 'bounce',
        string $closeEffect = 'fade',
        string $position = 'top-middle'
    ): void {
        $this->uiChanges()->add([
            'toast' => [
                'message' => $message,
                'type' => $type,
                'duration' => $duration,
                'open_effect' => $openEffect,
                'show_effect' => $showEffect,
                'close_effect' => $closeEffect,
                'position' => $position,
            ],
        ]);
    }

    /**
     * Requests to frontend to perform a redirect to the given URL.
     *
     * @param  string|null  $url  The URL to redirect to, or null to use intended redirect
     */
    public function redirect(?string $url = null): void
    {
        if ($url === null) {
            $url = redirect()->intended('/')->getTargetUrl();
        }

        $this->uiChanges()->add([
            'redirect' => $url,
        ]);
    }

    /**
     * Requests frontend to display an error / abort state.
     */
    protected function abort(int $statusCode, string $message = ''): void
    {
        $this->uiChanges()->add([
            'abort' => [
                'status_code' => $statusCode,
                'message' => $message,
            ],
        ]);
    }

    /**
     * Request frontend to change the current theme (e.g. 'light' or 'dark').
     */
    protected function changeTheme(string $theme): void
    {
        $this->uiChanges()->add([
            'change_theme' => $theme,
        ]);
    }

    /**
     * Requests frontend to change the current language (e.g. 'en' or 'es').
     */
    protected function changeLanguage(string $language): void
    {
        app()->setLocale($language);

        $this->uiChanges()->add([
            'change_language' => $language,
        ]);
    }
}
