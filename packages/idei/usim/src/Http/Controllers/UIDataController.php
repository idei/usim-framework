<?php

namespace Idei\Usim\Http\Controllers;

use Idei\Usim\Exceptions\UsimReadOnlyLifecycleException;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use SplFileInfo;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI Data Controller
 *
 * Handles read-only UI component data-fetching requests (GET) from the frontend.
 * Resolves the screen from component_id and dynamically routes queries to
 * screen methods prefixed with "get" (e.g. getUserAvatar, getOptions).
 */
class UIDataController extends Controller
{
    /**
     * Handle UI component data fetch request (GET)
     */
    public function handleFetch(Request $request): Response
    {
        $validated = $this->validateFetchRequest($request);

        $componentId = (int) $validated['component_id'];
        $action = $validated['action'];
        $parameters = $this->extractParameters($request);

        try {
            $callerScreenId = $this->extractCallerScreenId($parameters);
            /** @var class-string<Screen>|null $screenClass */
            $screenClass = $this->resolveScreenClass($componentId, $callerScreenId);

            if (! $screenClass) {
                return $this->screenNotFoundResponse();
            }

            $access = $screenClass::checkAccess();
            if (! $access['allowed']) {
                return $this->accessDeniedResponse($access);
            }

            $screen = Screen::make($screenClass);

            $method = $this->resolveQueryHandler($screen, $action);
            if ($method === null) {
                // If this screen is an active modal that does not implement the query,
                // fall back to the caller screen if the caller implements the query.
                $modalStack = UIStateManager::getClientActiveModalStack();
                foreach (array_reverse($modalStack) as $modalMeta) {
                    if ($modalMeta['modal_class'] === $screenClass && ! empty($modalMeta['caller_screen_class'])) {
                        $callerClass = $modalMeta['caller_screen_class'];
                        if (class_exists($callerClass) && is_a($callerClass, Screen::class, true)) {
                            $callerScreen = Screen::make($callerClass);
                            $callerMethod = $this->resolveQueryHandler($callerScreen, $action);
                            if ($callerMethod !== null) {
                                $screen = $callerScreen;
                                $screenClass = $callerClass;
                                $method = $callerMethod;
                                break;
                            }
                        }
                    }
                }
            }

            if ($method === null) {
                return $this->queryNotImplementedResponse($action);
            }

            $incomingStorage = $this->normalizeArray($request->input('storage', []));
            $queryParams = $request->query();

            $result = $screen->handleQuery(
                method: $method,
                parameters: $parameters,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                triggerComponentId: $componentId
            );

            return $this->formatQueryResponse($result);
        } catch (UsimReadOnlyLifecycleException $exception) {
            return response()->json([
                'error' => 'Read-only lifecycle violation',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->internalErrorResponse($exception);
        }
    }

    /**
     * @return array{
     *     component_id: int|string,
     *     action: string
     * }
     */
    private function validateFetchRequest(Request $request): array
    {
        return $request->validate([
            'component_id' => 'required|integer',
            'action' => 'required|string',
        ]);
    }

    /**
     * Extract and normalize parameters from the GET request.
     *
     * @return array<string, mixed>
     */
    private function extractParameters(Request $request): array
    {
        $rawParams = $request->query('parameters', []);

        if (\is_string($rawParams)) {
            $decoded = json_decode($rawParams, true);
            $parameters = \is_array($decoded) ? $decoded : [];
        } else {
            $parameters = $rawParams;
        }

        // Merge any additional query parameters that aren't system keys
        $reservedKeys = ['component_id', 'action', 'parameters', '_caller_screen_id', '_caller_service_id', 'storage'];
        $extraParams = $request->except($reservedKeys);

        return array_merge($extraParams, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function extractCallerScreenId(array &$parameters): ?int
    {
        $callerScreenId = $parameters['_caller_screen_id'] ?? $parameters['_caller_service_id'] ?? null;

        if (isset($parameters['_caller_screen_id'])) {
            unset($parameters['_caller_screen_id']);
        }
        if (isset($parameters['_caller_service_id'])) {
            unset($parameters['_caller_service_id']);
        }

        return $this->normalizeNullableInt($callerScreenId);
    }

    /**
     * @return class-string<Screen>|null
     */
    private function resolveScreenClass(int $componentId, ?int $callerScreenId): ?string
    {
        $context = null;

        if ($callerScreenId !== null) {
            $context = UIIdGenerator::getContextFromId($callerScreenId);
        } else {
            $context = UIIdGenerator::getContextFromId($componentId);
        }

        return is_string($context) && is_a($context, Screen::class, true) ? $context : null;
    }

    /**
     * Resolve action string to matching query method on the Screen.
     */
    public function resolveQueryHandler(Screen $screen, string $action): ?string
    {
        $candidateMethods = $this->candidateQueryMethodNames($action);

        foreach ($candidateMethods as $method) {
            if (method_exists($screen, $method)) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Generate list of candidate method names for a query action.
     *
     * Examples:
     * - user_avatar -> [getUserAvatar, user_avatar, userAvatar]
     * - get_user_avatar -> [getUserAvatar, getGetUserAvatar, get_user_avatar, getUserAvatar]
     * - avatar -> [getAvatar, avatar]
     *
     * @return array<int, string>
     */
    public function candidateQueryMethodNames(string $action): array
    {
        $methods = [];

        // 1. If starts with get_ (e.g. get_user_avatar -> getUserAvatar)
        if (str_starts_with(strtolower($action), 'get_')) {
            $clean = substr($action, 4);
            $methods[] = 'get'.Str::studly($clean);
        }

        // 2. Standard convention: snake_case -> getPascalCase (e.g. user_avatar -> getUserAvatar)
        $methods[] = 'get'.Str::studly($action);

        // 3. Exact action as method name (e.g. getUserAvatar or avatar)
        $methods[] = $action;

        // 4. camelCase (e.g. userAvatar)
        $methods[] = Str::camel($action);

        return array_values(array_unique($methods));
    }

    /**
     * Format heterogeneous PHP return values into appropriate HTTP Responses.
     */
    protected function formatQueryResponse(mixed $result): Response
    {
        // 1. Direct Symfony/Laravel Response instance
        if ($result instanceof Response) {
            return $result;
        }

        // 2. Null -> 204 No Content
        if ($result === null) {
            return response()->noContent();
        }

        // 3. Array, Collection or JsonSerializable -> JSON Response
        if (
            is_array($result) ||
            $result instanceof JsonSerializable ||
            $result instanceof Collection ||
            $result instanceof \stdClass
        ) {
            return response()->json($result);
        }

        // 4. File instances (SplFileInfo, UploadedFile, etc.)
        if ($result instanceof SplFileInfo) {
            return response()->file($result);
        }

        // 5. Open stream resource
        if (is_resource($result)) {
            return response()->stream(function () use ($result) {
                fpassthru($result);
                if (is_resource($result)) {
                    fclose($result);
                }
            }, 200, ['Content-Type' => 'application/octet-stream']);
        }

        // 6. String: detect if it's binary data (image/audio/video) or text/HTML/SVG
        if (is_string($result)) {
            // SVG vector graphic detection
            if (str_contains($result, '<svg') && str_contains($result, '</svg>')) {
                return response($result, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
            }

            // Check for binary buffer MIME type
            if (function_exists('finfo_buffer')) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($result) ?: '';

                if (
                    str_starts_with($mime, 'image/') ||
                    str_starts_with($mime, 'audio/') ||
                    str_starts_with($mime, 'video/') ||
                    $mime === 'application/pdf'
                ) {
                    return response($result, 200, ['Content-Type' => $mime]);
                }
            }

            // If string contains HTML tags
            if (str_contains($result, '<html') || str_contains($result, '<div') || str_contains($result, '<table')) {
                return response($result, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            // Plain string
            return response($result, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        // 7. Boolean, integer, float: return json
        return response()->json($result);
    }

    private function screenNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'Screen not found for this component',
        ], 404);
    }

    private function queryNotImplementedResponse(string $action): JsonResponse
    {
        return response()->json([
            'error' => "Query handler for action '{$action}' not implemented",
        ], 404);
    }

    /** @param array<string, mixed> $access */
    private function accessDeniedResponse(array $access): JsonResponse
    {
        $action = $access['action'] ?? null;
        $params = $this->normalizeArray($access['params'] ?? []);
        $response = [];

        if ($action === 'abort') {
            $response['abort'] = [
                'code' => $this->normalizeInt($params['code'] ?? 0),
                'message' => $this->normalizeString($params['message'] ?? ''),
            ];
        } elseif ($action === 'toast') {
            $response['toast'] = [
                'message' => $this->normalizeString($params['message'] ?? ''),
                'type' => $this->normalizeString($params['type'] ?? 'warning', 'warning'),
            ];
        } elseif ($action === 'redirect') {
            $response['redirect'] = $this->normalizeString($params['url'] ?? '');
        } else {
            $response['error'] = 'Access denied';
        }

        return response()->json($response, 403);
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

    /** @return array<string, mixed> */
    private function normalizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function normalizeInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) || is_float($value) || is_bool($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) || is_float($value) || is_bool($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}
