<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Layout\MainLayout;
use Idei\Usim\Layout\WorkbenchLayout;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screens\SidebarMenu;
use Idei\Usim\Screens\SidebarSubmenuPanel;
use Idei\Usim\Screens\StatusBar;

/**
 * Concrete ID generator for UI components.
 *
 * Implements ComponentIdGeneratorInterface with an isolated instance-level lifecycle,
 * making it safe for Laravel Octane / RoadRunner concurrent worker execution.
 */
class ComponentIdGenerator implements ComponentIdGeneratorInterface
{
    /**
     * Built-in package Screen, Layout, and Modal classes that may emit UI events or
     * data queries without being present in the consuming app's `bootstrap/cache/usim_screens.php`.
     *
     * @var list<class-string>
     */
    private const BUILTIN_CONTEXTS = [
        SidebarMenu::class,
        SidebarSubmenuPanel::class,
        StatusBar::class,
        WorkbenchLayout::class,
        MainLayout::class,
        ConfirmDialog::class,
    ];

    /** @var array<string, int> Auto-increment counter per context */
    private array $autoIncPerContext = [];

    /** @var array<string, array<int, true>> Reserved local IDs per context */
    private array $usedLocalIdsPerContext = [];

    /** @var array<string, array<string, int>> Stable local ID per named component and context */
    private array $namedLocalIdsPerContext = [];

    /** @var array<int, string> Mapping from offset to context class name */
    private array $offsetToContext = [];

    /** @var array<string, int> Mapping from context class name to offset */
    private array $contextOffsets = [];

    /** @var bool Flag to ensure services are loaded only once */
    private bool $servicesLoaded = false;

    /** @var bool Flag to ensure dynamic client contexts are probed at most once per request on miss */
    private bool $dynamicContextsLoaded = false;

    /** @var list<string> Context execution stack */
    private array $contextStack = [];

    public function pushCurrentContext(string $context): void
    {
        $this->contextStack[] = $context;
    }

    public function popCurrentContext(): ?string
    {
        return array_pop($this->contextStack);
    }

    public function getCurrentContext(): ?string
    {
        if (empty($this->contextStack)) {
            return null;
        }

        return end($this->contextStack);
    }

    /**
     * Generate an auto-incremented unique ID for a component within a context.
     */
    public function generate(string $context): int
    {
        if (! isset($this->autoIncPerContext[$context])) {
            $this->autoIncPerContext[$context] = 0;
        }

        $localId = $this->autoIncPerContext[$context];
        do {
            $localId++;
        } while (isset($this->usedLocalIdsPerContext[$context][$localId]));

        $this->autoIncPerContext[$context] = $localId;
        $this->usedLocalIdsPerContext[$context][$localId] = true;

        $offset = $this->getContextOffset($context);

        // Register offset → base context mapping for reverse lookup
        $baseContext = explode('@', $context, 2)[0];
        $this->offsetToContext[$offset] = $baseContext;

        return $offset + $localId;
    }

    /**
     * Generate a deterministic ID based on component name and context.
     */
    public function generateFromName(string $context, string $name): int
    {
        $baseContext = explode('@', $context, 2)[0];

        if (isset($this->namedLocalIdsPerContext[$context][$name])) {
            $localId = $this->namedLocalIdsPerContext[$context][$name];
            $offset = $this->getContextOffset($context);

            $this->offsetToContext[$offset] = $baseContext;

            return $offset + $localId;
        }

        $offset = $this->getContextOffset($context);

        $hash = crc32($name);
        $localId = (abs($hash) % 9999) + 1;

        while (isset($this->usedLocalIdsPerContext[$context][$localId])) {
            $localId++;
            if ($localId > 9999) {
                $localId = 1;
            }
        }

        $this->namedLocalIdsPerContext[$context][$name] = $localId;
        $this->usedLocalIdsPerContext[$context][$localId] = true;

        $this->offsetToContext[$offset] = $baseContext;

        return $offset + $localId;
    }

    /**
     * Get debug context information.
     *
     * @return array<string, mixed>
     */
    public function getContextInfo(string $context): array
    {
        return [
            'context' => $context,
            'offset' => $this->getContextOffset($context),
            'current_count' => $this->autoIncPerContext[$context] ?? 0,
        ];
    }

    /**
     * Reserve an already-existing component ID for a context.
     */
    public function reserveContextId(string $context, int $id): void
    {
        $offset = $this->getContextOffset($context);
        $localId = $id - $offset;

        if ($localId < 1) {
            return;
        }

        $this->usedLocalIdsPerContext[$context][$localId] = true;

        if (! isset($this->autoIncPerContext[$context]) || $this->autoIncPerContext[$context] < $localId) {
            $this->autoIncPerContext[$context] = $localId;
        }
    }

    /**
     * Register a context class/identifier so its deterministic offset is resolvable via getContextFromId().
     */
    public function registerContext(string $context): int
    {
        $this->ensureServicesLoaded();

        return $this->registerContextInternal($context);
    }

    /**
     * Get context class name from component ID.
     */
    public function getContextFromId(int $id): ?string
    {
        $this->ensureServicesLoaded();

        $offset = (int) floor($id / 10000) * 10000;

        if (isset($this->offsetToContext[$offset])) {
            return $this->offsetToContext[$offset];
        }

        // Defense in depth: probe configured layouts, custom app layouts, active/opened screens,
        // and stacked modals (@1, @2) for the current client on a cache miss.
        $this->loadDynamicClientContexts();

        return $this->offsetToContext[$offset] ?? null;
    }

    /**
     * Get the ID offset assigned to a context.
     */
    public function getContextOffset(string $context): int
    {
        $this->ensureServicesLoaded();

        return $this->computeContextOffset($context);
    }

    /**
     * Reset all counters and context stack.
     */
    public function reset(): void
    {
        $this->autoIncPerContext = [];
        $this->usedLocalIdsPerContext = [];
        $this->namedLocalIdsPerContext = [];
        $this->contextStack = [];
        $this->dynamicContextsLoaded = false;
    }

    /**
     * Lazy load registered UI services from the manifest file and built-in package contexts.
     */
    private function ensureServicesLoaded(): void
    {
        if ($this->servicesLoaded) {
            return;
        }

        $manifestPath = app()->bootstrapPath('cache/usim_screens.php');

        if (! file_exists($manifestPath)) {
            $manifest = [];
        } else {
            /** @var array<string, array{id_offset: int}> $manifest */
            $manifest = require $manifestPath;
        }

        foreach ($manifest as $className => $metadata) {
            $offset = $metadata['id_offset'];
            $this->offsetToContext[$offset] = $className;
            $this->contextOffsets[$className] = $offset;
        }

        $this->servicesLoaded = true;

        foreach (self::BUILTIN_CONTEXTS as $builtinClass) {
            if (class_exists($builtinClass) && ! isset($this->contextOffsets[$builtinClass])) {
                $this->registerContextInternal($builtinClass);
            }
        }
    }

    private function computeContextOffset(string $context): int
    {
        if ($context === 'default') {
            return 0;
        }

        if (isset($this->contextOffsets[$context])) {
            return $this->contextOffsets[$context];
        }

        $baseContext = explode('@', $context, 2)[0];

        $val = abs((int) crc32($context));
        $bucket = $val % 100000;
        $offset = $bucket * 10000;

        while (isset($this->offsetToContext[$offset]) && $this->offsetToContext[$offset] !== $baseContext) {
            $offset += 10000;
        }

        $this->contextOffsets[$context] = $offset;

        return $offset;
    }

    private function registerContextInternal(string $context): int
    {
        $trimmed = trim($context);
        if ($trimmed === '' || $trimmed === 'default') {
            return 0;
        }

        $offset = $this->computeContextOffset($trimmed);
        $baseContext = explode('@', $trimmed, 2)[0];

        if (! isset($this->offsetToContext[$offset])) {
            $this->offsetToContext[$offset] = $baseContext;
        }

        return $offset;
    }

    /**
     * Register runtime contexts active for the current client (configured layouts/shell screens,
     * opened screens, and stacked modals) when an ID bucket is not in the static manifest.
     */
    private function loadDynamicClientContexts(): void
    {
        if ($this->dynamicContextsLoaded) {
            return;
        }

        $this->dynamicContextsLoaded = true;

        try {
            $candidates = [];

            foreach ([
                config('usim.default_layout'),
                config('usim.guest_layout'),
                config('usim.default_menu_screen'),
                config('usim.default_sidebar_menu_screen'),
                config('usim.default_status_bar_screen'),
            ] as $configured) {
                if (\is_string($configured) && trim($configured) !== '' && class_exists($configured)) {
                    $candidates[] = $configured;
                }
            }

            $activeLayout = UIStateManager::getClientActiveLayout();
            if (\is_string($activeLayout) && $activeLayout !== '') {
                $candidates[] = $activeLayout;
            }

            $currentScreen = UIStateManager::getClientCurrentScreenClass();
            if (\is_string($currentScreen) && $currentScreen !== '') {
                $candidates[] = $currentScreen;
            }

            foreach (UIStateManager::getClientOpenedScreens() as $openedContext) {
                if ($openedContext !== '') {
                    $candidates[] = $openedContext;
                }
            }

            foreach (UIStateManager::getClientActiveModalStack() as $modalEntry) {
                $modalClass = $modalEntry['modal_class'] ?? null;
                if (\is_string($modalClass) && $modalClass !== '') {
                    $candidates[] = $modalClass;
                    $layerIndex = $modalEntry['layer_index'] ?? 0;
                    if (\is_int($layerIndex) && $layerIndex > 0) {
                        $candidates[] = "{$modalClass}@{$layerIndex}";
                    }
                }
            }

            foreach (array_unique($candidates) as $context) {
                $this->registerContextInternal($context);
            }
        } catch (\Throwable) {
            // Silently ignore when container/cache is unavailable in isolated unit tests
        }
    }
}
