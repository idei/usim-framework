<?php
namespace Idei\Usim\Listeners;

use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;

class UsimEventDispatcher
{
    // Cola estática para mantener los eventos pendientes en esta petición
    protected static array $eventQueue = [];
    protected static bool $isProcessing = false;
    protected static bool $deferProcessing = false;

    public function handle(UsimEvent $event): void
    {
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
        $methodName = 'on' . str_replace('_', '', ucwords($event->eventName, '_'));
        $openedScreens = UIStateManager::getClientOpenedScreens();
        $incomingStorage = request()->storage ?? [];

        foreach ($openedScreens as $rootComponentId) {
            $screenClass = UIIdGenerator::getContextFromId($rootComponentId);
            $screen = $this->instantiateScreen($screenClass);

            if ($methodName === 'onResetScreen') {
                $screen->onResetScreen();
                $screen->initializeEventContext($incomingStorage, debug: true);
                $screen->finalizeEventContext(debug: true);
                continue;
            }

            if (method_exists($screen, $methodName)) {
                $screen->initializeEventContext($incomingStorage, debug: true);
                $screen->$methodName($event->params);
                $screen->finalizeEventContext(debug: true);
            }
        }
    }

    private function instantiateScreen(string $screenClass): Screen
    {
        return app($screenClass);
    }
}
