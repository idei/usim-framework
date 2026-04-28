<?php

namespace Idei\Usim\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Idei\Usim\Screen;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\Support\UIIdGenerator;

/**
 * UI Event Controller
 *
 * Handles UI component events from the frontend.
 * Uses reflection to dynamically route events to screen methods
 * based on component ID and action name.
 *
 * Flow:
 * 1. Receive event from frontend (component_id, event, action, parameters)
 * 2. Resolve screen class from component ID using UIIdGenerator
 * 3. Convert action name to method name (snake_case → onPascalCase)
 * 4. Invoke method via reflection
 * 5. Return response (success/error + optional UI updates)
 */
class UIEventController extends Controller
{

    public function __construct(
        protected UIChangesCollector $uiChanges
    ) {
    }

    /**
     * Handle UI component event
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleEvent(Request $request): JsonResponse
    {
        $this->uiChanges->reset();
        $incomingStorage = $request->storage ?? [];
        $validated = $this->validateEventRequest($request);

        $componentId = (int) $validated['component_id'];
        $action = $validated['action'];
        $parameters = $validated['parameters'] ?? [];

        try {
            $callerScreenId = $this->extractCallerScreenId($parameters);
            /** @var class-string<Screen>|null $screenClass */
            $screenClass = $this->resolveScreenClass($componentId, $callerScreenId);

            if (!$screenClass) {
                return $this->screenNotFoundResponse();
            }

            $access = $screenClass::checkAccess();
            if (!$access['allowed']) {
                return $this->accessDeniedResponse($access);
            }

            $screen = $this->instantiateScreen($screenClass);
            $this->uiChanges->setStorage($incomingStorage);

            $method = $this->resolveActionHandler($screen, $action);
            if ($method === null) {
                return $this->actionNotImplementedResponse($action);
            }

            $screen->initializeEventContext($incomingStorage);
            $screen->$method($parameters);
            $screen->finalizeEventContext();

            return response()->json($this->uiChanges->all());
        } catch (\Throwable $exception) {
            return $this->internalErrorResponse($exception);
        }
    }

    private function validateEventRequest(Request $request): array
    {
        return $request->validate([
            'component_id' => 'required|integer',
            'event' => 'required|string',
            'action' => 'required|string',
            'parameters' => 'array',
        ]);
    }

    private function extractCallerScreenId(array &$parameters): ?int
    {
        // Backward-compatible: prefer _caller_screen_id, fallback to legacy _caller_service_id.
        $callerScreenId = $parameters['_caller_screen_id'] ?? $parameters['_caller_service_id'] ?? null;

        if (isset($parameters['_caller_screen_id'])) {
            unset($parameters['_caller_screen_id']);
        }
        if (isset($parameters['_caller_service_id'])) {
            unset($parameters['_caller_service_id']);
        }

        return $callerScreenId !== null ? (int) $callerScreenId : null;
    }

    /**
     * @return class-string<Screen>|null
     */
    private function resolveScreenClass(int $componentId, ?int $callerScreenId): ?string
    {
        if ($callerScreenId !== null) {
            return UIIdGenerator::getContextFromId($callerScreenId);
        }

        return UIIdGenerator::getContextFromId($componentId);
    }

    private function screenNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'Screen not found for this component',
        ], 404);
    }

    private function actionNotImplementedResponse(string $action): JsonResponse
    {
        return response()->json([
            'error' => "Action '{$action}' not implemented",
        ], 404);
    }

    private function accessDeniedResponse(array $access): JsonResponse
    {
        $action = $access['action'];
        $params = $access['params'];
        $response = [];

        if ($action === 'abort') {
            $response['abort'] = [
                'code' => $params['code'],
                'message' => $params['message'],
            ];
        } elseif ($action === 'toast') {
            $response['toast'] = [
                'message' => $params['message'],
                'type' => $params['type'] ?? 'warning',
            ];
        } elseif ($action === 'redirect') {
            $response['redirect'] = $params['url'];
        } else {
            $response['error'] = 'Access denied';
        }

        return response()->json($response);
    }

    /**
     * @param class-string<Screen> $screenClass
     */
    private function instantiateScreen(string $screenClass): Screen
    {
        /** @var mixed $screen */
        $screen = app($screenClass);

        if (!$screen instanceof Screen) {
            throw new \RuntimeException("Resolved screen [{$screenClass}] is not a valid Screen instance.");
        }

        return $screen;
    }

    private function internalErrorResponse(\Throwable $exception): JsonResponse
    {
        return response()->json([
            'error' => 'Internal server error',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => config('app.debug') ? $exception->getMessage() : null,
            'trace' => config('app.debug') ? $exception->getTraceAsString() : null,
        ], 500);
    }

    private function resolveActionHandler(Screen $screen, string $action): ?string
    {
        $method = $this->actionToMethodName($action);

        return is_callable([$screen, $method]) ? $method : null;
    }

    /**
     * Convert action name to method name
     *
     * Convention: snake_case → onPascalCase
     * Examples:
     * - test_action → onTestAction
     * - submit_form → onSubmitForm
     * - cancel_form → onCancelForm
     * - open_settings → onOpenSettings
     *
     * @param string $action Action name in snake_case
     * @return string Method name in onPascalCase format
     */
    private function actionToMethodName(string $action): string
    {
        // Replace underscores with spaces, capitalize words, remove spaces
        $pascalCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));

        return 'on' . $pascalCase;
    }
}
