<?php

namespace Idei\Usim\Layout;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractLayout extends Screen implements LayoutInterface
{
    public static ?string $layout = null;

    protected static ?AbstractLayout $currentLayout = null;

    /** @var class-string<Screen>|null */
    protected static ?string $activeHostScreen = null;

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
     * @return class-string<Screen>|null
     */
    public static function getActiveHostScreen(): ?string
    {
        return self::$activeHostScreen;
    }

    /**
     * @param  class-string<Screen>|null  $screenClass
     */
    public static function setActiveHostScreen(?string $screenClass): void
    {
        self::$activeHostScreen = $screenClass;
    }

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
    public function getSlot(string $name = 'content'): ?Container
    {
        if (isset($this->slots[$name])) {
            return $this->slots[$name];
        }

        $fallback = match ($name) {
            'menu', 'main_menu', 'top_menu' => $this->mainMenuContainer,
            'main', 'center', 'content' => $this->contentContainer,
            default => null,
        };

        if ($fallback instanceof Container) {
            return $fallback;
        }

        // When the layout was reconstructed from snapshot cache, resolve by container name
        if (isset($this->container)) {
            $candidateNames = match ($name) {
                'menu', 'main_menu', 'top_menu' => ['main_menu_container', 'menu_container', 'top_menu'],
                'main', 'center', 'content' => ['content_container', 'main_container', 'main', 'content'],
                default => [$name, "{$name}_container", "{$name}_slot"],
            };

            foreach ($candidateNames as $candidate) {
                $found = $this->container->findByName($candidate);
                if ($found instanceof Container) {
                    $this->slots[$name] = $found;
                    if (in_array($name, ['main', 'center', 'content'], true)) {
                        $this->contentContainer = $found;
                    } elseif (in_array($name, ['menu', 'main_menu', 'top_menu'], true)) {
                        $this->mainMenuContainer = $found;
                    }

                    return $found;
                }
            }
        }

        return null;
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

    protected function postLoadUI(): void
    {
        parent::postLoadUI();

        // Restore slot references when loaded from cached snapshot
        if (isset($this->container)) {
            if ($this->contentContainer === null) {
                $content = $this->container->findByName('content_container');
                if ($content instanceof Container) {
                    $this->contentContainer = $content;
                    $this->registerSlot('main', $content);
                    $this->registerSlot('content', $content);
                }
            }
            if ($this->mainMenuContainer === null) {
                $menu = $this->container->findByName('main_menu_container');
                if ($menu instanceof Container) {
                    $this->mainMenuContainer = $menu;
                    $this->registerSlot('top_menu', $menu);
                    $this->registerSlot('menu', $menu);
                }
            }
        }
    }

    /**
     * Default base UI implementation for Layout Screens.
     * Subclasses can override this to configure layout containers and slots.
     */
    protected function buildBaseUI(Container $container, ...$params): void {}

    /**
     * Backward-compatible bridge to build layout structure with a content builder callback.
     *
     * @param  Closure(Container): void  $contentBuilder
     * @param  class-string<Screen>|null  $menuScreen
     */
    public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void
    {
        $this->buildBaseUI($root);
        $slot = $this->getSlot('main') ?? $this->getSlot('content') ?? $root;
        $contentBuilder($slot);
    }

    /**
     * Get the active screen class or slug currently occupying a slot.
     *
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
     * @param  class-string<Screen>|string  $screenClass
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
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Target slot name (e.g. 'main', 'top_menu', 'sidebar')
     * @param  array<int|string, mixed>  $params  Parameters passed to the screen
     * @param  bool  $updateBrowserUrl  Whether to push HTML5 browser history state
     * @param  bool  $force  Whether to force reload the slot even if the target screen class matches current screen
     */
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true, bool $force = false): bool
    {
        // Support polymorphic argument order: showInto($slot, $screenClass) or showInto($screenClass, $slot)
        if ($slot !== null && (class_exists($slot) || Screen::resolveScreenClassFromSlug($slot) !== null)) {
            $temp = $screenClass;
            $screenClass = $slot;
            $slot = $temp;
        }

        $effectiveSlot = $slot ?? 'main';
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass === null || ! class_exists($targetClass) || ! is_subclass_of($targetClass, Screen::class)) {
            throw new RuntimeException("Target screen [{$screenClass}] is not a valid Screen instance.");
        }

        $access = $targetClass::checkAccess();
        if (! $access['allowed']) {
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

        $targetContainer = $this->getSlot($effectiveSlot);
        if (! $targetContainer instanceof Container) {
            Log::warning("Slot [{$effectiveSlot}] not found on layout [".static::class.'].');

            return false;
        }

        // Clear existing content in the slot container
        $targetContainer->clear();
        Screen::embedInto($targetClass, $targetContainer);
        $this->setActiveScreen($effectiveSlot, $targetClass);

        $routeSlug = Screen::resolveScreenSlug($targetClass);
        if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            $routePath = $targetClass::getRoutePath();
            UIStateManager::setClientCurrentScreen($routePath, $targetClass);
            app(UIChangesCollector::class)->add([
                'navigate' => [
                    'url' => $routePath,
                    'route' => $routeSlug,
                    'title' => $targetClass::getMenuLabel(),
                    'slot' => $targetContainer->getName() ?? $effectiveSlot,
                    'slot_container_id' => $targetContainer->getId(),
                ],
                'clear_container' => $targetContainer->getId(),
            ]);
        } else {
            app(UIChangesCollector::class)->add([
                'clear_container' => $targetContainer->getId(),
            ]);
        }

        return true;
    }

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param  class-string<Screen>|string  $screenClass
     * @param  array<int|string, mixed>  $params
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
            $effectiveSlot = $slot ?? $targetClass::getDefaultSlot();
        } else {
            $effectiveSlot = $slot ?? 'main';
        }

        return $this->showInto($screenClass, $effectiveSlot, $params);
    }
}
