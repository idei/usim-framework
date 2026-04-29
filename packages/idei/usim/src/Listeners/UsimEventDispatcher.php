<?php
namespace Idei\Usim\Listeners;

use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;

class UsimEventDispatcher
{
    public function handle(UsimEvent $event): void
    {
        // Convertir el nombre del evento a nombre de método
        // "logged_user" -> "onLoggedUser"
        $methodName = 'on' . str_replace('_', '', ucwords($event->eventName, '_'));

        // $rootComponents  = UIStateManager::getRootComponents();
        $openedScreens = UIStateManager::getClientOpenedScreens();
        $incomingStorage = request()->storage ?? [];

        //foreach ($rootComponents as $parent => $rootComponentId) {
        foreach ($openedScreens as $rootComponentId) {
            $serviceClass = UIIdGenerator::getContextFromId($rootComponentId);

            // Instantiate service
            $service = app($serviceClass);

            if (method_exists($service, $methodName)) {
                // If the method is "onResetScreen", we want to call it before the event context
                // is initialized, so that the screen is reset before processing the event.
                if ($methodName === 'onResetScreen') {
                    $service->$methodName();
                }

                $service->initializeEventContext($incomingStorage, debug: true);

                if ($methodName !== 'onResetScreen') {
                    // If the method is not "onResetScreen", we want to call it after the event context is initialized, so that the screen is updated with the new event context before processing the event.
                    $service->$methodName($event->params);
                    $finalizedResult = $service->finalizeEventContext(debug: true);
                }
            }
        }
    }
}
