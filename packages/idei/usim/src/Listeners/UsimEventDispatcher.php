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

    public function handle(UsimEvent $event): void
    {
        // // 1. Encolamos el evento entrante
        // self::$eventQueue[] = $event;

        // // 2. Si ya hay un proceso en marcha, nos retiramos.
        // // El bucle "while" original se encargará de procesar este nuevo evento.
        // if (self::$isProcessing) {
        //     Log::info("Evento '{$event->eventName}' encolado. Actualmente procesando otro evento, se procesará después.");
        //     return;
        // }

        // self::$isProcessing = true;

        // // 3. Procesamos la cola hasta que no queden eventos (FIFO)
        // while ($currentEvent = array_shift(self::$eventQueue)) {
        $this->processEvent($event);
        // }

        // self::$isProcessing = false;
    }

    protected function processEvent(UsimEvent $event): void
    {
        $methodName = 'on' . str_replace('_', '', ucwords($event->eventName, '_'));
        $openedScreens = UIStateManager::getClientOpenedScreens($event->params['client_id']);
        //$incomingStorage = request()->storage ?? [];
        $incomingStorage = $event->params['storage'];

        foreach ($openedScreens as $rootComponentId) {
            $screenClass = UIIdGenerator::getContextFromId($rootComponentId);
            $screen = $this->instantiateScreen($screenClass);

            if ($methodName === 'onResetScreen') {
                $screen->onResetScreen();
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
