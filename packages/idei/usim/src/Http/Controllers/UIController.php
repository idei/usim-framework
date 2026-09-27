<?php

namespace Idei\Usim\Http\Controllers;

use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class UIController extends Controller
{
    public function __construct(
        protected UIChangesCollector $uiChanges
    ) {}

    /**
     * Show UI for the specified screen service.
     *
     * Supports optional 'reset' query parameter to clear cached data for the screen.
     *
     * @param  string  $screenRoute  The screen route from the URL (e.g., 'admin/dashboard')
     */
    public function show(string $screenRoute): JsonResponse
    {
        $this->uiChanges->reset();

        $screenClass = $this->resolveScreenClass($screenRoute);

        if (! class_exists($screenClass)) {
            return $this->screenNotFoundResponse($screenRoute);
        }

        $accessResult = $screenClass::checkAccess();

        if (! $accessResult['allowed']) {
            return $this->accessDeniedResponse($accessResult);
        }

        $requestData = $this->extractRequestData();
        $screen = Screen::make($screenClass);

        $screen->render(
            incomingStorage: $requestData['storage'],
            queryParams: $requestData['queryParams'],
            parent: request()->query('parent', 'main'),
            shouldReset: $requestData['shouldReset']
        );

        $activeModal = UIStateManager::getClientActiveModal();
        if ($activeModal !== null && class_exists($activeModal['modal_class'])) {
            /** @var class-string<Screen> $modalClass */
            $modalClass = $activeModal['modal_class'];
            $isSameCaller = $activeModal['caller_screen_class'] === null
                || $activeModal['caller_screen_class'] === $screenClass;

            if ($isSameCaller && ! $requestData['shouldReset']) {
                Screen::restoreActiveModal(
                    activeModal: $activeModal,
                    caller: $screen,
                    incomingStorage: $requestData['storage'],
                    queryParams: $requestData['queryParams'],
                );
            } else {
                UIStateManager::clearClientActiveModal();
            }
        }

        $allChanges = $this->uiChanges->all();

        // // copia allChanges y sólo deja los cambios que tengan 'type' => 'row' o 'type' => 'cell'
        // $filteredChanges = array_filter($allChanges, function ($change) {
        //     $type = $change['type'] ?? null;
        //     $name = $change['name'] ?? null;
        //     $isTableCell = $type === 'tablecell';
        //     $nameContainsAnumberBetween1And20 = true;// isset($name) && preg_match('/^users_table__(1[0-9]|20|[0-9])_/', $name);
        //     $rowBetween1And20 = isset($change['row']);// && $change['row'] >= 0 && $change['row'] <= 20;
        //     return $rowBetween1And20 || ($isTableCell && $nameContainsAnumberBetween1And20);
        // });

        // // sanitiza los cambios filtrados para que sólo queden los campos 'type', 'name' y 'row' (si existe)
        // $filteredChanges = array_map(function ($change) {
        //     $type = $change['type'] ?? null;
        //     if ($type === 'tablecell') {
        //         return [
        //             'column' => $change['column'] ?? null,
        //             // 'parent' => $change['parent'] ?? null,
        //             'text' => $change['text'] ?? null,
        //             // 'type' => $type,
        //             // 'name' => $change['name'] ?? null,
        //         ];
        //     }
        //     return [
        //         'row' => $change['row'] ?? null,
        //         // 'parent' => $change['parent'] ?? null,
        //         // 'type' => $change['type'] ?? null,
        //     ];
        // }, $filteredChanges);

        // Log::info("Changes: " . json_encode(
        //     $filteredChanges,
        //     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        // ));

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
            ->map(fn (string $segment) => Str::studly($segment))
            ->join('\\');

        $namespaceValue = config('usim.screens_namespace', 'App\\UI\\Screens');
        $namespace = \is_string($namespaceValue) ? $namespaceValue : 'App\\UI\\Screens';

        return "{$namespace}\\{$screenNameSegments}";
    }

    /**
     * @return array{shouldReset: bool, storage: array<string, mixed>, queryParams: array<string, mixed>}
     */
    private function extractRequestData(): array
    {
        $storageCandidate = request()->input('storage', []);
        /** @var array<string, mixed> $storage */
        $storage = is_array($storageCandidate) ? $storageCandidate : [];

        /** @var array<string, mixed> $queryParams */
        $queryParams = request()->query();

        return [
            'shouldReset' => (bool) request()->query('reset', false),
            'storage' => $storage,
            'queryParams' => $queryParams,
        ];
    }

    private function screenNotFoundResponse(string $screenRoute): JsonResponse
    {
        return response()->json([
            'error' => 'Screen not found',
            'screen' => $screenRoute,
        ], 404);
    }

    /**
     * @param  array{allowed: bool, action: string|null, params: array<string, mixed>}  $accessResult
     */
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
}
