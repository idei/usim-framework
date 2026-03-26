<?php
namespace Idei\Usim\Listeners;

use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Services\Support\UIIdGenerator;
use Idei\Usim\Services\Support\UIStateManager;

class UsimEventDispatcher
{
    public function handle(UsimEvent $event): void
    {
        // Convertir el nombre del evento a nombre de método
        // "logged_user" -> "onLoggedUser"
        $methodName = 'on' . str_replace('_', '', ucwords($event->eventName, '_'));

        $rootComponents  = UIStateManager::getRootComponents();
        $incomingStorage = request()->storage ?? [];

        foreach ($rootComponents as $parent => $rootComponentId) {
            $serviceClass = UIIdGenerator::getContextFromId($rootComponentId);

            // Instantiate service
            $service = app($serviceClass);
            if (method_exists($service, $methodName)) {
                $service->initializeEventContext($incomingStorage, debug: true);

                // Invoke handler method
                $methodResult = $service->$methodName($event->params);
                $finalizedResult = $service->finalizeEventContext(debug: true);
            }
        }
    }
}
