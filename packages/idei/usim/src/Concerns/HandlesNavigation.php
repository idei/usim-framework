<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use RuntimeException;

/**
 * Navigation, slots, and client meta-actions logic for Screen.
 *
 * @mixin Screen
 */
trait HandlesNavigation
{
    /**
     * Show a target screen inside a specific slot container or layout slot.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Slot name (e.g., 'main', 'content', 'sidebar')
     * @param  array<int|string, mixed>  $params  Parameters passed to target's buildBaseUI
     * @param  bool  $updateBrowserUrl  Whether to update browser location history for main content navigation
     * @param  bool  $force  Whether to force reload the slot even if the target screen class matches current screen
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

        $layout = $this->getLayout();
        $currentScreen = $layout?->getActiveScreen($effectiveSlot);
        if ($currentScreen === null && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            $currentScreen = UIStateManager::getClientCurrentScreenClass();
        }

        // If the target screen is already active in the slot, no need to reload unless forced
        if (! $force && $currentScreen !== null) {
            $currentClass = class_exists($currentScreen) ? $currentScreen : Screen::resolveScreenClassFromSlug($currentScreen);
            if ($currentClass === $targetClass) {
                return true;
            }
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
        if (isset($this->container)) {
            $found = $this->container->findByName($effectiveSlot);
            if ($found instanceof Container) {
                $targetContainer = $found;
            } elseif ($effectiveSlot === 'main' || $effectiveSlot === 'content') {
                $foundContent = $this->container->findByName('content_container');
                if ($foundContent instanceof Container) {
                    $targetContainer = $foundContent;
                }
            }
        }

        // Only clear and embed locally if target container is physically present in this screen's tree
        if ($targetContainer instanceof Container) {
            $targetContainer->clear();
            Screen::embedInto($targetClass, $targetContainer);
        }

        // Update browser URL and instruct frontend to load screen into slot
        if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            UIStateManager::setClientCurrentScreen($routePath, (string) $targetClass);
            $this->uiChanges()->add([
                'navigate' => [
                    'url' => $routePath,
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
