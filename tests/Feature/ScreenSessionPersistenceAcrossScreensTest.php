<?php

use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

it('preserves user authentication session across multiple continuous reactive screen transitions', function () {
    /** @var TestCase $test */
    $test = $this;

    // 1. Iniciar sesión y obtener token
    $loginResult = $test->loginAs(
        role: 'root',
        withScreenPermission: TranslateManager::class
    );
    $loginResult['response']->assertOk();

    $storagePayload = $loginResult['response']->json('storage');
    $storageKey = array_key_first($storagePayload ?? []);
    $rawStorage = $storagePayload[$storageKey] ?? '';

    $decodedStorage = json_decode($rawStorage, true);
    expect($decodedStorage)->toBeArray();
    expect($decodedStorage['store_token'] ?? null)->not->toBeEmpty();
    $authToken = $decodedStorage['store_token'];

    $tabClientId = 'tab-' . (string) Str::uuid();

    // 2. Primera interacción: pantalla TranslateManager que requiere autenticación
    $screenResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode([
            'store_token' => $authToken,
            'store_theme' => 'dark',
        ]))
        ->getJson(screenApiUrl(TranslateManager::class, ['reset' => true]));

    $screenResponse->assertOk();
    $searchComponent = findComponentByName($screenResponse->json(), 'search_translations');
    expect($searchComponent)->not->toBeNull();
    $searchComponentId = (int) ($searchComponent['_json_key'] ?? 0);

    // 3. Ejecutar múltiples acciones consecutivas en TranslateManager (simulando flujos rápidos y continuos)
    for ($i = 1; $i <= 3; $i++) {
        $searchQuery = 'query_step_' . $i . '_' . Str::random(4);

        $eventResponse = $test
            ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
            ->withHeader('X-USIM-Storage', json_encode([
                'store_token' => $authToken,
                'store_theme' => 'dark',
            ]))
            ->postJson('/api/ui-event', [
                'component_id' => $searchComponentId,
                'event' => 'input',
                'action' => 'search_translations',
                'parameters' => ['value' => $searchQuery],
            ]);

        $eventResponse->assertOk();
        // Verificar que Auth::check() sigue siendo true en el backend
        expect(Auth::check())->toBeTrue();
        expect(Auth::user())->not->toBeNull();

        // Verificar que si el backend devuelve storage, no clobberiza store_token
        $eventStorage = $eventResponse->json('storage.' . $storageKey);
        if ($eventStorage !== null) {
            $eventStorageData = json_decode($eventStorage, true);
            if (is_array($eventStorageData) && array_key_exists('store_token', $eventStorageData)) {
                expect($eventStorageData['store_token'])->toBe($authToken);
            }
        }
    }

    // 4. Transición a Home screen (pantalla que no declara store_token)
    $homeResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode([
            'store_token' => $authToken,
            'store_theme' => 'dark',
        ]))
        ->getJson(screenApiUrl(Home::class, ['reset' => true]));

    $homeResponse->assertOk();
    expect(Auth::check())->toBeTrue();

    // 5. Cargar Menu screen (declara protected string $store_token = '')
    $menuResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode([
            'store_token' => $authToken,
            'store_theme' => 'dark',
        ]))
        ->getJson(screenApiUrl(Menu::class, ['parent' => 'menu']));

    $menuResponse->assertOk();
    expect(Auth::check())->toBeTrue();

    $menuStorage = $menuResponse->json('storage.' . $storageKey);
    if ($menuStorage !== null) {
        $menuStorageData = json_decode($menuStorage, true);
        if (is_array($menuStorageData) && array_key_exists('store_token', $menuStorageData)) {
            // El backend no debe haber vaciado store_token a ''
            expect($menuStorageData['store_token'])->toBe($authToken);
        }
    }

    // 6. Volver a TranslateManager: el usuario SIGUE autenticado sin haber sido deslogueado silenciosamente
    $reloadResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode([
            'store_token' => $authToken,
            'store_theme' => 'dark',
        ]))
        ->getJson(screenApiUrl(TranslateManager::class));

    $reloadResponse->assertOk();
    expect(Auth::check())->toBeTrue();
    expect(findComponentByName($reloadResponse->json(), 'search_translations'))->not->toBeNull();
});

it('properly clears session and emits empty store_token upon explicit confirm_logout', function () {
    /** @var TestCase $test */
    $test = $this;

    $loginResult = $test->loginAs(role: 'root');
    $loginResult['response']->assertOk();

    $storagePayload = $loginResult['response']->json('storage');
    $storageKey = array_key_first($storagePayload ?? []);
    $rawStorage = $storagePayload[$storageKey] ?? '';
    $decodedStorage = json_decode($rawStorage, true);
    $authToken = $decodedStorage['store_token'];

    $tabClientId = 'tab-logout-' . (string) Str::uuid();

    $uiResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode(['store_token' => $authToken]))
        ->getJson(screenApiUrl(Menu::class, ['parent' => 'menu']));

    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $logoutResponse = $test
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabClientId)
        ->withHeader('X-USIM-Storage', json_encode(['store_token' => $authToken]))
        ->postJson('/api/ui-event', [
            'component_id' => $componentId,
            'event' => 'click',
            'action' => 'confirm_logout',
            'parameters' => [],
        ]);

    $logoutResponse->assertOk();
    expect(Auth::check())->toBeFalse();

    $logoutStorage = $logoutResponse->json('storage.' . $storageKey);
    $logoutStorageData = json_decode($logoutStorage, true);

    // Upon explicit logout, store_token MUST be cleared
    expect($logoutStorageData['store_token'] ?? '')->toBe('');
});

