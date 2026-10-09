<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;

/**
 * Modal overlay lifecycle and management logic for Screen.
 *
 * @mixin Screen
 */
trait HandlesModals
{
    /**
     * Open a screen as a modal dialog.
     *
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $queryParams
     * @param  class-string<Screen>|null  $screenClass
     */
    public static function openAsModal(
        array $params = [],
        ?Screen $caller = null,
        ?string $callbackAction = null,
        array $queryParams = [],
        ?string $screenClass = null,
    ): Screen {
        $targetClass = $screenClass ?? static::class;
        $instance = Screen::make($targetClass);
        $instance->parent = 'modal';

        if ($caller !== null) {
            $instance->callerScreenId = $caller->getScreenComponentId();
            $instance->callerScreenClass = $caller::class;
            $instance->callbackAction = $callbackAction;
        } elseif ((isset($params['callerServiceId']) && is_numeric($params['callerServiceId'])) || (isset($params['caller_service_id']) && is_numeric($params['caller_service_id']))) {
            $rawCallerId = $params['callerServiceId'] ?? $params['caller_service_id'];
            if (is_int($rawCallerId) || (is_string($rawCallerId) && ctype_digit($rawCallerId))) {
                $instance->callerScreenId = (int) $rawCallerId;
                $context = UIIdGenerator::getContextFromId($instance->callerScreenId);
                if (is_string($context) && is_a($context, Screen::class, true)) {
                    $instance->callerScreenClass = $context;
                }
            }
            $instance->callbackAction = $callbackAction;
        }

        $currentStack = UIStateManager::getClientActiveModalStack();
        $sameClassCount = 0;
        foreach ($currentStack as $entry) {
            if ($entry['modal_class'] === $instance::class) {
                $sameClassCount++;
            }
        }
        $instance->modalLayerIndex = $sameClassCount;

        // Clear any previous cached snapshot so the modal renders fresh
        $instance->clearCachedScreenSnapshot();

        $instance->render(
            incomingStorage: Screen::$currentIncomingStorage,
            queryParams: $queryParams,
            parent: 'modal',
            shouldReset: false,
            buildParams: $params,
        );

        // Store active modal state in UIStateManager for F5 / reconnection persistence
        UIStateManager::storeClientActiveModal(
            modalClass: $instance::class,
            callerScreenId: $instance->callerScreenId,
            callbackAction: $instance->callbackAction,
            params: $params,
            layerIndex: $instance->modalLayerIndex,
            callerScreenClass: $instance->callerScreenClass,
        );

        return $instance;
    }

    /**
     * Helper to open any screen as a modal from within the current screen.
     *
     * @param  class-string<Screen>  $screenClass
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $queryParams
     */
    protected function openModal(
        string $screenClass,
        array $params = [],
        ?string $callbackAction = null,
        array $queryParams = [],
    ): Screen {
        return self::openAsModal(
            params: $params,
            caller: $this,
            callbackAction: $callbackAction,
            queryParams: $queryParams,
            screenClass: $screenClass,
        );
    }

    /**
     * Determine whether this screen is currently opened as a modal overlay.
     */
    public function isOpenedAsModal(): bool
    {
        if ($this->parent === 'modal') {
            return true;
        }

        if (isset($this->container) && $this->container->getParent() === 'modal') {
            return true;
        }

        $activeStack = UIStateManager::getClientActiveModalStack();
        foreach ($activeStack as $modalMeta) {
            if ($modalMeta['modal_class'] === static::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Restore and re-render an active modal for F5 / browser reload.
     *
     * @param  array{modal_class: class-string<Screen>|string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>}  $activeModal
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     */
    public static function restoreActiveModal(
        array $activeModal,
        Screen $caller,
        array $incomingStorage = [],
        array $queryParams = [],
    ): ?Screen {
        $modalClass = $activeModal['modal_class'];
        if (! class_exists($modalClass) || ! is_a($modalClass, self::class, true)) {
            return null;
        }

        $instance = Screen::make($modalClass);
        $instance->parent = 'modal';
        $instance->callerScreenId = $caller->getScreenComponentId();
        $instance->callerScreenClass = $caller::class;
        $instance->callbackAction = $activeModal['callback_action'];

        $instance->render(
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            parent: 'modal',
            shouldReset: false,
            buildParams: $activeModal['params'],
        );

        UIStateManager::storeClientActiveModal(
            modalClass: $instance::class,
            callerScreenId: $instance->callerScreenId,
            callbackAction: $instance->callbackAction,
            params: $activeModal['params'],
        );

        return $instance;
    }

    /**
     * Restore and re-render an active modal stack for F5 / browser reload.
     *
     * @param  list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index?: int, page_screen_route?: ?string}>  $modalStack
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @return list<Screen>
     */
    public static function restoreActiveModalStack(
        array $modalStack,
        Screen $caller,
        array $incomingStorage = [],
        array $queryParams = [],
    ): array {
        $restoredScreens = [];
        /** @var array<string, Screen> $screensByClass */
        $screensByClass = [];
        $screensByClass[$caller::class] = $caller;

        $updatedStack = [];
        $countsByClass = [];

        foreach ($modalStack as $modalMeta) {
            $modalClass = $modalMeta['modal_class'];
            if (! class_exists($modalClass) || ! is_a($modalClass, Screen::class, true)) {
                continue;
            }

            $instance = Screen::make($modalClass);
            $instance->parent = 'modal';

            $layerIndex = $modalMeta['layer_index'] ?? ($countsByClass[$modalClass] ?? 0);
            $countsByClass[$modalClass] = $layerIndex + 1;
            $instance->modalLayerIndex = $layerIndex;

            $callerScreenClass = $modalMeta['caller_screen_class'];
            $callerScreenId = $modalMeta['caller_screen_id'];

            if ($callerScreenClass !== null && isset($screensByClass[$callerScreenClass])) {
                $effectiveCaller = $screensByClass[$callerScreenClass];
            } elseif ($callerScreenClass !== null && class_exists($callerScreenClass) && is_a($callerScreenClass, Screen::class, true)) {
                $effectiveCaller = Screen::make($callerScreenClass);
                $screensByClass[$callerScreenClass] = $effectiveCaller;
            } else {
                $effectiveCaller = $caller;
            }

            $instance->callerScreenId = $callerScreenId ?? $effectiveCaller->getScreenComponentId();
            $instance->callerScreenClass = $effectiveCaller::class;
            $instance->callbackAction = $modalMeta['callback_action'];

            $instance->render(
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: 'modal',
                shouldReset: false,
                buildParams: $modalMeta['params'],
            );

            $screensByClass[$instance::class] = $instance;
            $restoredScreens[] = $instance;

            $updatedStack[] = [
                'modal_class' => $instance::class,
                'caller_screen_id' => $instance->callerScreenId,
                'caller_screen_class' => $instance->callerScreenClass,
                'callback_action' => $instance->callbackAction,
                'params' => $modalMeta['params'],
                'layer_index' => $instance->modalLayerIndex,
                'page_screen_route' => $modalMeta['page_screen_route'] ?? null,
            ];
        }

        UIStateManager::setClientActiveModalStack($updatedStack);

        return $restoredScreens;
    }

    /**
     * Action called when modal is dismissed from client.
     *
     * @param  array<string, mixed>  $params
     */
    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Sends 'close_modal' action to frontend and clears modal snapshot from cache.
     */
    public function closeModal(): void
    {
        $this->uiChanges()->add([
            'action' => 'close_modal',
        ]);

        if ($this->parent === 'modal') {
            $this->clearCachedScreenSnapshot();
            try {
                UIStateManager::removeClientOpenedScreen($this->getScreenComponentId());
            } catch (\Throwable) {
                // Ignore if container is not initialized
            }
        }

        $popped = UIStateManager::popClientActiveModal();
        if ($popped !== null) {
            $layerIndex = $popped['layer_index'];
            $contextKey = $layerIndex > 0 ? $popped['modal_class'].'@'.$layerIndex : $popped['modal_class'];
            UIStateManager::clear($contextKey);
        }
    }

    /**
     * Close the current modal and return data to the caller screen.
     *
     * @param  string|array<string, mixed>|null  $action
     * @param  array<string, mixed>  $parameters
     */
    protected function returnToCaller(string|array|null $action = null, array $parameters = []): void
    {
        if (is_array($action)) {
            $parameters = $action;
            $action = null;
        }

        $targetAction = $action ?? $this->callbackAction;
        $callerScreenClass = $this->callerScreenClass;

        if ($callerScreenClass === null || $targetAction === null) {
            $activeModal = UIStateManager::getClientActiveModal();
            if ($activeModal !== null) {
                /** @var class-string<Screen>|null $resolvedCaller */
                $resolvedCaller = $activeModal['caller_screen_class'];
                $callerScreenClass ??= $resolvedCaller;
                $targetAction ??= $activeModal['callback_action'];
            }
        }

        $this->closeModal();

        if ($callerScreenClass !== null && $targetAction !== null && class_exists($callerScreenClass)) {
            if (! str_starts_with($targetAction, 'on')) {
                $targetAction = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $targetAction)));
            }

            $caller = Screen::make($callerScreenClass);
            $caller->handleAction(
                method: $targetAction,
                parameters: $parameters,
                incomingStorage: $this->incomingStorage,
            );
        }
    }

    /**
     * Update modal content dynamically.
     *
     * @param  array<string, mixed>  $content
     */
    public function updateModal(array $content): void
    {
        $this->uiChanges()->add([
            'action' => 'update_modal',
            'content' => $content,
        ]);
    }
}
