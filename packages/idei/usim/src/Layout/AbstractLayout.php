<?php

namespace Idei\Usim\Layout;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractLayout
{
    /** @var self|null */
    protected static ?AbstractLayout $currentLayout = null;

    protected ?Container $mainMenuContainer = null;
    protected ?Container $contentContainer = null;

    /** @var array<string, Container> */
    protected array $slots = [];

    /** @var array<string, class-string<Screen>|string> */
    protected array $activeScreens = [];

    /**
     * Get the currently active layout instance in the request/session context.
     */
    public static function current(): ?self
    {
        if (self::$currentLayout !== null) {
            return self::$currentLayout;
        }

        if (app()->bound(self::class)) {
            /** @var self $layout */
            $layout = app(self::class);
            return $layout;
        }

        return null;
    }

    /**
     * Set the currently active layout instance.
     */
    public static function setCurrent(?AbstractLayout $layout): void
    {
        self::$currentLayout = $layout;
        if ($layout !== null) {
            app()->instance(self::class, $layout);
            app()->instance($layout::class, $layout);
        }
    }

    /**
     * Build the layout structure wrapping the screen content slot.
     *
     * @param Container $root Root container of the host screen
     * @param Closure(Container): void $contentBuilder Callback that builds the screen content inside the slot
     * @param class-string<Screen>|null $menuScreen Custom menu screen class if declared by the host screen
     */
    abstract public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void;

    /**
     * Register a container as a named slot.
     */
    public function registerSlot(string $name, Container $container): Container
    {
        return $this->slots[$name] = $container;
    }

    /**
     * Get a registered slot container by name.
     */
    public function getSlot(string $name): ?Container
    {
        return $this->slots[$name] ?? match ($name) {
            'menu', 'main_menu', 'top_menu' => $this->mainMenuContainer,
            'main', 'center', 'content' => $this->contentContainer,
            default => null,
        };
    }

    /**
     * Get all registered slots.
     *
     * @return array<string, Container>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    /**
     * Get the active screen class or slug currently occupying a slot.
     *
     * @param string $slot
     * @return class-string<Screen>|string|null
     */
    public function getActiveScreen(string $slot = 'main'): ?string
    {
        if (isset($this->activeScreens[$slot])) {
            return $this->activeScreens[$slot];
        }

        if (in_array($slot, ['main', 'content', 'center'], true)) {
            return UIStateManager::getClientCurrentScreenClass();
        }

        return null;
    }

    /**
     * Set the active screen class or slug occupying a slot.
     *
     * @param string $slot
     * @param class-string<Screen>|string $screenClass
     */
    public function setActiveScreen(string $slot, string $screenClass): self
    {
        $this->activeScreens[$slot] = $screenClass;
        return $this;
    }

    /**
     * Get the main menu container / slot.
     */
    public function getMainMenuContainer(): ?Container
    {
        return $this->mainMenuContainer;
    }

    /**
     * Get the content container / slot.
     */
    public function getContentContainer(): ?Container
    {
        return $this->contentContainer;
    }

    /**
     * Mount and display a target screen inside a layout slot.
     *
     * @param string $slot Target slot name (e.g. 'main', 'top_menu', 'sidebar')
     * @param class-string<Screen>|string $screenClass Target screen class or slug
     * @param array<int|string, mixed> $params Parameters passed to the screen
     * @param bool $updateBrowserUrl Whether to push HTML5 browser history state
     */
    public function showInto(string $slot, string $screenClass, array $params = [], bool $updateBrowserUrl = true): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass === null || !class_exists($targetClass) || !is_subclass_of($targetClass, Screen::class)) {
            throw new RuntimeException("Target screen [{$screenClass}] is not a valid Screen instance.");
        }

        $access = $targetClass::checkAccess();
        if (!$access['allowed']) {
            $redirectUrl = $access['params']['url'] ?? null;
            if (($access['action'] ?? null) === 'redirect' && is_string($redirectUrl) && $redirectUrl !== '') {
                app(UIChangesCollector::class)->add(['redirect' => $redirectUrl]);
            } else {
                $code = $access['params']['code'] ?? 403;
                $message = $access['params']['message'] ?? 'Unauthorized';
                app(UIChangesCollector::class)->add([
                    'abort' => [
                        'status_code' => is_int($code) ? $code : 403,
                        'message' => is_string($message) ? $message : 'Unauthorized',
                    ],
                ]);
            }
            return false;
        }

        $targetContainer = $this->getSlot($slot);
        if (!$targetContainer instanceof Container) {
            Log::warning("Slot [{$slot}] not found on layout [" . static::class . "].");
            return false;
        }

        // Clear existing content in the slot container
        $targetContainer->clear();
        Screen::embedInto($targetClass, $targetContainer);

        $routeSlug = Screen::resolveScreenSlug($targetClass);
        if ($updateBrowserUrl && in_array($slot, ['main', 'content', 'center'], true)) {
            $routePath = $targetClass::getRoutePath();
            UIStateManager::setClientCurrentScreen($routePath, $targetClass);
            app(UIChangesCollector::class)->add([
                'navigate' => [
                    'url' => $routePath,
                    'route' => $routeSlug,
                    'title' => $targetClass::getMenuLabel(),
                    'slot' => $targetContainer->getName() ?? $slot,
                ],
            ]);
        }

        return true;
    }

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param class-string<Screen>|string $screenClass
     * @param string|null $slot
     * @param array<int|string, mixed> $params
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
            $effectiveSlot = $slot ?? $targetClass::getDefaultSlot();
        } else {
            $effectiveSlot = $slot ?? 'main';
        }

        return $this->showInto($effectiveSlot, $screenClass, $params);
    }
}

