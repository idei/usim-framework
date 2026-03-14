<?php

use Idei\Usim\Http\Middleware\PrepareUIContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('always exposes storage as an array when no encrypted state is provided', function () {
    $middleware = new PrepareUIContext();
    $request = Request::create('/api/ui/demo/menu', 'GET');

    $response = $middleware->handle($request, function (Request $request): JsonResponse {
        return response()->json([
            'storage' => $request->input('storage'),
        ]);
    });

    expect($response->getData(true)['storage'])->toBe([]);
});

it('normalizes decrypted non-array storage payloads to an empty array', function () {
    $middleware = new PrepareUIContext();
    $request = Request::create('/api/ui/demo/menu', 'GET', [
        'usim' => encrypt('null'),
    ]);

    $response = $middleware->handle($request, function (Request $request): JsonResponse {
        return response()->json([
            'storage' => $request->input('storage'),
        ]);
    });

    expect($response->getData(true)['storage'])->toBe([]);
});

it('registers usim routes with PrepareUIContext middleware', function () {
    $screenRoute = Route::getRoutes()->getByName('api.screen');
    $eventRoute = Route::getRoutes()->getByName('ui.event');
    $filesRoute = Route::getRoutes()->getByName('files.serve');

    expect($screenRoute)->not->toBeNull();
    expect($eventRoute)->not->toBeNull();
    expect($filesRoute)->not->toBeNull();

    expect($screenRoute->gatherMiddleware())->toContain(PrepareUIContext::class);
    expect($eventRoute->gatherMiddleware())->toContain(PrepareUIContext::class);
    expect($filesRoute->gatherMiddleware())->toContain(PrepareUIContext::class);
});
