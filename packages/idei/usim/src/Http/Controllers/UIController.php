<?php
namespace Idei\Usim\Http\Controllers;

use Idei\Usim\Screen;
use Idei\Usim\UIChangesCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
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
            $screen->onResetScreen();
        }

        $this->initializeScreenContext($screen, $requestData);
        $this->injectAgentContext($screen);

        $allChanges = $this->uiChanges->all();

        // copia allChanges y sólo deja los cambios que tengan 'type' => 'row' o 'type' => 'cell'
        $filteredChanges = array_filter($allChanges, function ($change) {
            $type = $change['type'] ?? null;
            $name = $change['name'] ?? null;
            $isTableCell = $type === 'tablecell';
            $nameContainsAnumberBetween1And20 = isset($name) && preg_match('/^users_table__(1[0-9]|20|[1-9])_/', $name);
            $rowBetween1And20 = isset($change['row']) && $change['row'] >= 1 && $change['row'] <= 20;
            return $rowBetween1And20 || ($isTableCell && $nameContainsAnumberBetween1And20);
        });

        // sanitiza los cambios filtrados para que sólo queden los campos 'type', 'name' y 'row' (si existe)
        $filteredChanges = array_map(function ($change) {
            $type = $change['type'] ?? null;
            if ($type === 'tablecell') {
                return [
                    'column' => $change['column'] ?? null,
                    'parent' => $change['parent'] ?? null,
                    'text' => $change['text'] ?? null,
                    'type' => $type,
                    'name' => $change['name'] ?? null,
                ];
            }
            return [
                'row' => $change['row'] ?? null,
                'parent' => $change['parent'] ?? null,
                'type' => $change['type'] ?? null,
            ];
        }, $filteredChanges);


        Log::info("Changes: " . json_encode(
            $filteredChanges,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return response()->json($allChanges);
    }

    /**
     * Convert URL route to fully qualified screen class name.
     *
     * Examples:
     * - 'admin/dashboard' -> 'App\UI\Screens\Admin\UsersManager'
     * - 'demos/input-demo' -> 'App\UI\Screens\Demos\InputDemo'
     */
    private function resolveScreenClass(string $screenRoute): string
    {
        $screenNameSegments = collect(explode('/', $screenRoute))
            ->map(fn(string $segment) => Str::studly($segment))
            ->join('\\');

        $namespace = config('usim.screens_namespace', 'App\\UI\\Screens');

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
