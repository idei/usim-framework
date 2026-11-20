<?php

namespace Idei\Usim\Listeners;

use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;

class UsimEventDispatcher
{
    // Cola estática para mantener los eventos pendientes en esta petición
    /** @var list<UsimEvent> */
    protected static array $eventQueue = [];

    protected static bool $isProcessing = false;

    protected static bool $deferProcessing = false;

    /** @var array<string, bool> */
    protected static array $processedEventHashes = [];

    public function handle(UsimEvent $event): void
    {
        $eventHash = spl_object_hash($event);
        if (isset(self::$processedEventHashes[$eventHash])) {
            return;
        }
        self::$processedEventHashes[$eventHash] = true;

        // 1. Encolamos el evento entrante
        self::$eventQueue[] = $event;

        // 2. Si ya hay un proceso en marcha, nos retiramos.
        // El bucle "while" original se encargará de procesar este nuevo evento.
        if (self::$deferProcessing || self::$isProcessing) {
            return;
        }

        $this->drainQueue();
    }

    public static function beginDeferredProcessing(): void
    {
        self::$deferProcessing = true;
    }

    public static function endDeferredProcessing(): void
    {
        self::$deferProcessing = false;
    }

    public static function flushQueuedEvents(): void
    {
        app(self::class)->drainQueue();
    }

    public static function resetRequestState(): void
    {
        self::$eventQueue = [];
        self::$isProcessing = false;
        self::$deferProcessing = false;
        self::$processedEventHashes = [];
    }

    private function drainQueue(): void
    {
        if (self::$isProcessing) {
            return;
        }

        self::$isProcessing = true;

        try {
            // 3. Procesamos la cola hasta que no queden eventos (FIFO)
            while ($currentEvent = array_shift(self::$eventQueue)) {
                $this->processEvent($currentEvent);
            }
        } finally {
            self::$isProcessing = false;
        }
    }

    protected function processEvent(UsimEvent $event): void
    {
        $methodName = Screen::actionToMethodName($event->eventName);
        $openedScreens = UIStateManager::getClientOpenedScreens();

        /** @var array<string, bool> $targetScreenClasses */
        $targetScreenClasses = [];

        foreach ($openedScreens as $rootComponentId) {
            $screenClass = UIIdGenerator::getContextFromId((int) $rootComponentId);
            if (\is_string($screenClass) && $screenClass !== '') {
                $targetScreenClasses[$screenClass] = true;
            }
        }

        foreach (UIStateManager::getClientOpenedClasses() as $openedClass) {
            if ($openedClass !== '') {
                $targetScreenClasses[$openedClass] = true;
            }
        }

        // Asegurar notificación de las pantallas de shell activas (menú / sidebar / status bar)
        // Solo si no estamos en una pantalla explícitamente standalone ($layout = null, ej. Kiosk/Pairing)
        $currentScreen = UIStateManager::getClientCurrentScreenClass();
        $isStandaloneActive = UIStateManager::hasClientActiveLayout()
            && UIStateManager::getClientActiveLayout() === null;
        if (! $isStandaloneActive && \is_string($currentScreen) && class_exists($currentScreen) && is_subclass_of($currentScreen, Screen::class)) {
            $isStandaloneActive = $currentScreen::$layout === null;
        }

        if (! $isStandaloneActive) {
            $isUiRequest = self::$deferProcessing || request()->is('api/ui*') || request()->is('ui*') || request()->hasHeader('X-UI-Client-Id');
            $defaultMenuConfig = config('usim.default_menu_screen', 'App\\UI\\Screens\\Menu');
            $defaultMenuScreen = \is_string($defaultMenuConfig) ? $defaultMenuConfig : 'App\\UI\\Screens\\Menu';

            // Resiliently notify default_menu_screen if no layout or MainLayout is active;
            // Workbench shell screens (SidebarMenu, StatusBar) are notified when active in the mounted layout or opened screens.
            $mountedLayout = UIStateManager::getClientActiveLayout();
            $shellCandidates = [];

            if ($mountedLayout === null || str_ends_with($mountedLayout, 'MainLayout')) {
                $shellCandidates[] = $defaultMenuScreen;
            } else {
                $sidebarScreen = config('usim.default_sidebar_menu_screen');
                $statusBarScreen = config('usim.default_status_bar_screen');
                if (\is_string($sidebarScreen) && $sidebarScreen !== '') {
                    $shellCandidates[] = $sidebarScreen;
                }
                if (\is_string($statusBarScreen) && $statusBarScreen !== '') {
                    $shellCandidates[] = $statusBarScreen;
                }
            }

            foreach ($shellCandidates as $shellClass) {
                if (class_exists($shellClass) && ($isUiRequest || isset($targetScreenClasses[$shellClass]))) {
                    $targetScreenClasses[$shellClass] = true;
                }
            }
        }

        // Asegurar notificación de la pantalla actualmente activa
        if (\is_string($currentScreen) && class_exists($currentScreen)) {
            $targetScreenClasses[$currentScreen] = true;
        }

        // Asegurar notificación del modal activo PRIMERO (para que cierre el modal y emita toast antes de cambios de layout)
        $activeModal = UIStateManager::getClientActiveModal();
        $activeModalClass = (\is_array($activeModal) && ! empty($activeModal['modal_class']) && \is_string($activeModal['modal_class']) && class_exists($activeModal['modal_class']))
            ? $activeModal['modal_class']
            : null;
        if ($activeModalClass !== null) {
            unset($targetScreenClasses[$activeModalClass]);
            $targetScreenClasses = [$activeModalClass => true] + $targetScreenClasses;
        }

        $incomingStorageRaw = request()->storage ?? [];
        /** @var array<string, mixed> $incomingStorage */
        $incomingStorage = [];
        if (\is_array($incomingStorageRaw)) {
            foreach ($incomingStorageRaw as $key => $value) {
                if (\is_string($key)) {
                    $incomingStorage[$key] = $value;
                }
            }
        }

        if (app()->bound(UIChangesCollector::class)) {
            /** @var UIChangesCollector $collector */
            $collector = app(UIChangesCollector::class);
            $incomingStorage = array_merge($incomingStorage, $collector->getStorage());
        }

        $hadRootLayoutSwitchBefore = app()->bound(UIChangesCollector::class)
            && app(UIChangesCollector::class)->get('clear_container') === 'main';

        $allShellScreens = array_filter([
            config('usim.default_menu_screen', 'App\\UI\\Screens\\Menu'),
            config('usim.default_sidebar_menu_screen'),
            config('usim.default_status_bar_screen'),
        ], static fn (mixed $v): bool => \is_string($v) && $v !== '');

        foreach (array_keys($targetScreenClasses) as $screenClass) {
            // If an earlier handler in this event already triggered a full #main layout switch,
            // skip remaining shell screens (Menu/SidebarMenu/StatusBar) so they don't overwrite the freshly rendered layout tree,
            // while still allowing non-shell handlers (like Login modal closing itself) to run.
            if (! $hadRootLayoutSwitchBefore
                && in_array($screenClass, $allShellScreens, true)
                && app()->bound(UIChangesCollector::class)
                && app(UIChangesCollector::class)->get('clear_container') === 'main'
            ) {
                continue;
            }

            if (! class_exists($screenClass) || (! is_subclass_of($screenClass, Screen::class) && $screenClass !== Screen::class)) {
                continue;
            }

            try {
                $screen = Screen::make($screenClass);
            } catch (\Throwable) {
                continue;
            }

            if ($methodName !== 'onResetScreen' && ! method_exists($screen, $methodName)) {
                continue;
            }

            $queryParams = Screen::getCurrentQueryParams();
            if (empty($queryParams)) {
                $queryParams = request()->query();
            }

            $screen->handleAction(
                method: $methodName,
                parameters: $event->params,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
            );
        }
    }
}
