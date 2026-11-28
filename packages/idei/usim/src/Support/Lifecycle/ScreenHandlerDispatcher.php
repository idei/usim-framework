<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use BadMethodCallException;
use Idei\Usim\Components\Container;
use Idei\Usim\Exceptions\UIRequestValidationException;
use Idei\Usim\Exceptions\UsimReadOnlyLifecycleException;
use Idei\Usim\Screen;
use Idei\Usim\Support\MethodParameterResolver;
use ReflectionMethod;

/**
 * Dispatches Screen action and query handlers with strongly-typed parameter resolution,
 * automatic UIRequest validation error binding, service-backed component fallback,
 * and read-only mutation guardrails.
 */
final class ScreenHandlerDispatcher
{
    public function __construct(
        private readonly MethodParameterResolver $parameterResolver,
        private readonly ComponentServiceBindingResolver $serviceBindingResolver = new ComponentServiceBindingResolver,
    ) {}

    /**
     * Invoke a Screen action handler or fall back to a service-backed component action.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     */
    public function dispatchAction(
        Screen $screen,
        string $method,
        array $parameters,
        array $queryParams,
        array $incomingStorage,
        ?int $triggerComponentId
    ): void {
        if (method_exists($screen, $method)) {
            $this->invokeActionMethod($screen, $method, $parameters, $queryParams, $incomingStorage, $triggerComponentId);

            return;
        }

        $binding = $this->serviceBindingResolver->resolveForScreen($screen, $triggerComponentId, $method, queryOnly: false);
        if ($binding !== null) {
            $this->serviceBindingResolver->executeAction($binding, $parameters, $screen);
        }
    }

    /**
     * Invoke a read-only Screen query handler or fall back to a service-backed component query.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     */
    public function dispatchQuery(
        Screen $screen,
        string $method,
        array $parameters,
        array $queryParams,
        array $incomingStorage,
        ?int $triggerComponentId
    ): mixed {
        if (method_exists($screen, $method)) {
            return $this->invokeScreenMethod($screen, $method, $parameters, $queryParams, $incomingStorage, $triggerComponentId);
        }

        $binding = $this->serviceBindingResolver->resolveForScreen($screen, $triggerComponentId, $method, queryOnly: true);
        if ($binding !== null) {
            return $this->serviceBindingResolver->executeQuery($binding, $parameters, $queryParams, $screen, $method);
        }

        throw new BadMethodCallException("Query method [{$method}] does not exist on screen [".get_class($screen).'].');
    }

    /**
     * Guard GET query execution against accidental component tree mutations.
     *
     * @param  array<int, array<string, mixed>>  $dirtyChanges
     */
    public function assertNoReadOnlyMutations(Screen $screen, string $method, array $dirtyChanges): void
    {
        if (empty($dirtyChanges)) {
            return;
        }

        throw new UsimReadOnlyLifecycleException(
            'Component tree mutations are not permitted inside GET query methods ['.get_class($screen)."::{$method}()]. ".
                'GET methods are reserved exclusively for delivering data/media payloads to components. '.
                'If you need to mutate the UI, use a POST event action [on*()] instead.'
        );
    }

    /**
     * Determine whether a service-backed component in the container handles the given action/query.
     */
    public function supportsComponentHandler(
        Container $container,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): bool {
        return $this->serviceBindingResolver->resolve($container, $componentId, $action, $queryOnly) !== null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     */
    private function invokeActionMethod(
        Screen $screen,
        string $method,
        array $parameters,
        array $queryParams,
        array $incomingStorage,
        ?int $triggerComponentId
    ): void {
        try {
            $this->invokeScreenMethod($screen, $method, $parameters, $queryParams, $incomingStorage, $triggerComponentId);
        } catch (UIRequestValidationException $validationException) {
            $this->handleValidationException($screen, $validationException);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     */
    private function invokeScreenMethod(
        Screen $screen,
        string $method,
        array $parameters,
        array $queryParams,
        array $incomingStorage,
        ?int $triggerComponentId
    ): mixed {
        $reflectionMethod = new ReflectionMethod($screen, $method);
        $arguments = $this->parameterResolver->resolve(
            reflectionMethod: $reflectionMethod,
            screen: $screen,
            parameters: $parameters,
            queryParams: $queryParams,
            incomingStorage: $incomingStorage,
            triggerComponentId: $triggerComponentId
        );

        return $reflectionMethod->invokeArgs($screen, $arguments);
    }

    private function handleValidationException(Screen $screen, UIRequestValidationException $validationException): void
    {
        $uiRequest = $validationException->uiRequest;
        if (! $uiRequest || ! $uiRequest->shouldAutoBindErrors()) {
            throw $validationException;
        }

        $uiRequest->bindErrorsToScreen($screen, $validationException->validator->errors());

        $toastMsg = $uiRequest->errorToastMessage();
        if ($toastMsg !== null) {
            $screen->toast($toastMsg, 'error');
        }

        $uiRequest->failedValidation($validationException->validator);
    }
}
