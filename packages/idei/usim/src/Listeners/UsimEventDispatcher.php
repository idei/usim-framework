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

        // Asegurar notificación de la pantalla de menú predeterminada si Menu está abierta
        // o si nos encontramos en el ciclo de un evento UI (UIEventController / petición UI)
        $defaultMenuConfig = config('usim.default_menu_screen', 'App\\UI\\Screens\\Menu');
        $defaultMenuScreen = \is_string($defaultMenuConfig) ? $defaultMenuConfig : 'App\\UI\\Screens\\Menu';
        if (class_exists($defaultMenuScreen)) {
            $isUiRequest = self::$deferProcessing || request()->is('api/ui*') || request()->is('ui*') || request()->hasHeader('X-UI-Client-Id');
            if ($isUiRequest || isset($targetScreenClasses[$defaultMenuScreen])) {
                $targetScreenClasses[$defaultMenuScreen] = true;
            }
        }

        // Asegurar notificación de la pantalla actualmente activa
        $currentScreen = UIStateManager::getClientCurrentScreenClass();
        if (\is_string($currentScreen) && class_exists($currentScreen)) {
            $targetScreenClasses[$currentScreen] = true;
        }

        // Asegurar notificación del modal activo si existe
        $activeModal = UIStateManager::getClientActiveModal();
        if (\is_array($activeModal) && ! empty($activeModal['modal_class']) && class_exists($activeModal['modal_class'])) {
            $targetScreenClasses[$activeModal['modal_class']] = true;
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

        foreach (array_keys($targetScreenClasses) as $screenClass) {
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

            $screen->handleAction(
                method: $methodName,
                parameters: $event->params,
                incomingStorage: $incomingStorage,
            );
        }
    }
}
