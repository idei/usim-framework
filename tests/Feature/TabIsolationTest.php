<?php

use App\UI\Screens\Admin\TranslateManager;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Str;
use Tests\TestCase;

it('prioritizes X-UI-Client-Id header over cookie in UIStateManager', function () {
    $cookieClientId = 'cookie-client-' . Str::uuid();
    $headerClientId = 'tab-client-' . Str::uuid();

    $request = \Illuminate\Http\Request::create('/api/test', 'GET', [], [UIStateManager::CLIENT_ID_COOKIE => $cookieClientId], [], [
        'HTTP_X_UI_CLIENT_ID' => $headerClientId,
    ]);
    app()->instance('request', $request);

    expect(UIStateManager::getOrCreateClientId())->toBe($headerClientId);
});

it('falls back to cookie when X-UI-Client-Id header is absent', function () {
    $cookieClientId = 'cookie-client-' . Str::uuid();

    $request = \Illuminate\Http\Request::create('/api/test', 'GET', [], [UIStateManager::CLIENT_ID_COOKIE => $cookieClientId]);
    app()->instance('request', $request);

    expect(UIStateManager::getOrCreateClientId())->toBe($cookieClientId);
});

it('treats a tab without store_token as guest even if browser has authenticated session cookie', function () {
    /** @var TestCase $test */
    $test = $this;

    // 1. Authenticate user in browser
    $loginResult = $test->loginAs(
        role: 'root',
        withScreenPermission: TranslateManager::class
    );
    $loginResult['response']->assertOk();

    $storagePayload = $loginResult['response']->json('storage');
    $storageKey = array_key_first($storagePayload ?? []);
    $rawStorage = $storagePayload[$storageKey] ?? '';
    expect($rawStorage)->toContain('store_token');

    $browserCookieId = 'browser-' . (string) Str::uuid();
    $tabAId = 'tab-a-' . (string) Str::uuid();
    $tabBId = 'tab-b-' . (string) Str::uuid();

    // 2. Tab A sends its store_token: should be authenticated and access TranslateManager
    $responseTabA = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabAId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->getJson(screenApiUrl(TranslateManager::class, ['reset' => true]));

    $responseTabA->assertOk();
    $searchA = findComponentByName($responseTabA->json(), 'search_translations');
    expect($searchA)->not->toBeNull();

    // 3. Tab B opens in the SAME browser (same cookie), but has NO store_token in sessionStorage
    // It should be treated as Guest and redirected to login!
    $responseTabB = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabBId)
        ->withHeader('X-USIM-Storage', '')
        ->getJson(screenApiUrl(TranslateManager::class, ['reset' => true]));

    $responseTabB->assertOk();
    // TranslateManager requires authentication: unauthorized guest receives redirect action to /auth/login
    expect($responseTabB->json('redirect'))->toBe(url('/auth/login'));
});

it('isolates state across multiple tabs sharing the same browser cookie via X-UI-Client-Id and store_token', function () {
    /** @var TestCase $test */
    $test = $this;

    $loginResult = $test->loginAs(
        role: 'root',
        withScreenPermission: TranslateManager::class
    );
    $loginResult['response']->assertOk();

    $storagePayload = $loginResult['response']->json('storage');
    $storageKey = array_key_first($storagePayload ?? []);
    $rawStorage = $storagePayload[$storageKey] ?? '';
    expect($rawStorage)->toContain('store_token');

    // Simulates a single browser: same cookie client ID shared across tabs
    $browserCookieId = 'browser-' . (string) Str::uuid();

    $tabAId = 'tab-a-' . (string) Str::uuid();
    $tabBId = 'tab-b-' . (string) Str::uuid();

    // 1. Initialize Tab A with its token
    $initialTabA = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabAId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->getJson(screenApiUrl(TranslateManager::class, ['reset' => true]));
    $initialTabA->assertOk();

    $searchA = findComponentByName($initialTabA->json(), 'search_translations');
    expect($searchA)->not->toBeNull();
    $searchAId = (int) ($searchA['_json_key'] ?? 0);

    // 2. Initialize Tab B with its token
    $initialTabB = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabBId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->getJson(screenApiUrl(TranslateManager::class, ['reset' => true]));
    $initialTabB->assertOk();

    $searchB = findComponentByName($initialTabB->json(), 'search_translations');
    expect($searchB)->not->toBeNull();
    $searchBId = (int) ($searchB['_json_key'] ?? 0);

    // 3. Mutate Tab A
    $searchValA = 'query_for_tab_a_' . Str::random(5);
    $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabAId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->postJson('/api/ui-event', [
            'component_id' => $searchAId,
            'event' => 'input',
            'action' => 'search_translations',
            'parameters' => ['value' => $searchValA],
        ])
        ->assertOk();

    // 4. Mutate Tab B with a different query
    $searchValB = 'query_for_tab_b_' . Str::random(5);
    $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabBId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->postJson('/api/ui-event', [
            'component_id' => $searchBId,
            'event' => 'input',
            'action' => 'search_translations',
            'parameters' => ['value' => $searchValB],
        ])
        ->assertOk();

    // 5. Reload Tab A and verify Tab A retained its own state
    $reloadTabA = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabAId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->getJson(screenApiUrl(TranslateManager::class));
    $reloadTabA->assertOk();

    $reloadedSearchA = findComponentByName($reloadTabA->json(), 'search_translations');
    expect($reloadedSearchA)->not->toBeNull();
    expect((string) ($reloadedSearchA['value'] ?? ''))->toBe($searchValA);

    // 6. Reload Tab B and verify Tab B retained its own state without collision
    $reloadTabB = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $browserCookieId)
        ->withHeader(UIStateManager::CLIENT_ID_HEADER, $tabBId)
        ->withHeader('X-USIM-Storage', $rawStorage)
        ->getJson(screenApiUrl(TranslateManager::class));
    $reloadTabB->assertOk();

    $reloadedSearchB = findComponentByName($reloadTabB->json(), 'search_translations');
    expect($reloadedSearchB)->not->toBeNull();
    expect((string) ($reloadedSearchB['value'] ?? ''))->toBe($searchValB);
});
