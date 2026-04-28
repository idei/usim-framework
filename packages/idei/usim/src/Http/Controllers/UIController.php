<?php
namespace Idei\Usim\Http\Controllers;

use Illuminate\Routing\Controller;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class UIController extends Controller
{
    public function __construct(
        protected UIChangesCollector $uiChanges
    ) {
    }

    /**
     * Show UI for the specified screen service.
     *
     * Supports optional 'reset' query parameter to clear cached data for the screen.
     *
     * @param string $screenRoute The screen route from the URL (e.g., 'admin/dashboard')
     * @return JsonResponse
     */
    public function show(string $screenRoute): JsonResponse
    {
        $this->uiChanges->reset();

        $screenClass = $this->resolveScreenClass($screenRoute);

        if (!class_exists($screenClass)) {
            return $this->screenNotFoundResponse($screenRoute);
        }

        $accessResult = $screenClass::checkAccess();

        if (!$accessResult['allowed']) {
            return $this->accessDeniedResponse($accessResult);
        }

        $requestData = $this->extractRequestData();
        $screen = $this->instantiateScreen($screenClass, $requestData);

        if ($requestData['shouldReset']) {
            $this->resetScreen($screen);
        }

        $this->initializeScreenContext($screen, $requestData);
        $this->injectAgentContext($screen);

        return response()->json($this->uiChanges->all());
    }

    /**
     * Convert URL route to fully qualified screen class name.
     *
     * Examples:
     * - 'admin/dashboard' -> 'App\UI\Screens\Admin\Dashboard'
     * - 'demos/input-demo' -> 'App\UI\Screens\Demos\InputDemo'
     */
    private function resolveScreenClass(string $screenRoute): string
    {
        $screenNameSegments = collect(explode('/', $screenRoute))
            ->map(fn(string $segment) => Str::studly($segment))
            ->join('\\');

        $namespace = config('ui-services.screens_namespace', 'App\\UI\\Screens');

        return "{$namespace}\\{$screenNameSegments}";
    }

    private function extractRequestData(): array
    {
        return [
            'shouldReset' => request()->query('reset', false),
            'storage' => request()->storage ?? [],
            'queryParams' => request()->query(),
        ];
    }

    private function screenNotFoundResponse(string $screenRoute): JsonResponse
    {
        return response()->json([
            'error' => 'Screen not found',
            'screen' => $screenRoute,
        ], 404);
    }

    private function accessDeniedResponse(array $accessResult): JsonResponse
    {
        $action = $accessResult['action'];
        $params = $accessResult['params'];
        $response = [];

        if ($action === 'redirect') {
            $response['redirect'] = $params['url'];
        } elseif ($action === 'abort') {
            $response['abort'] = [
                'code' => $params['code'],
                'message' => $params['message'],
            ];
        }

        return response()->json($response);
    }

    private function instantiateScreen(string $screenClass, array $requestData): Screen
    {
        $this->uiChanges->setStorage($requestData['storage']);
        $screen = app($screenClass);

        if (!$screen instanceof Screen) {
            abort(500, "Resolved screen [{$screenClass}] is not a valid Screen instance.");
        }

        return $screen;
    }

    private function resetScreen(Screen $screen): void
    {
        $screen->clearStoredUI();
        $screen->onResetService();
    }

    private function initializeScreenContext(Screen $screen, array $requestData): void
    {

        $screen->initializeEventContext(
            incomingStorage: $requestData['storage'],
            queryParams: $requestData['queryParams']
        );

        $screen->finalizeEventContext(reload: true);
    }

    private function injectAgentContext(Screen $screen): void
    {
        $agentContext = $screen->getAgentContext();

        if (!empty($agentContext)) {
            $this->uiChanges->add(['agent_context' => $agentContext]);
        }
    }
}
