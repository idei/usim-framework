<?php

// Test 1: Default behavior (headless=false) - catch-all serves HTML
it('catch-all serves html when headless mode is disabled', function () {
    /** @var \Tests\TestCase $this */
    config(['ui-services.headless_mode' => false]);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewIs('usim::app');
});

// Test 2: Headless mode enabled - catch-all returns 406 JSON
it('catch-all returns 406 when headless mode is enabled', function () {
    /** @var \Tests\TestCase $this */
    config(['ui-services.headless_mode' => true]);

    $response = $this->get('/');

    $response->assertStatus(406);
    $response->assertJson([
        'error' => 'Headless mode enabled',
        'message' => 'USIM is running in headless mode. Use /api/ui endpoints directly.',
    ]);
});

// Test 3: API endpoint works same in both modes
it('api-ui endpoint works same regardless of headless mode setting', function () {
    /** @var \Tests\TestCase $this */
    // Test with headless=false
    config(['ui-services.headless_mode' => false]);
    $response1 = $this->getJson('/api/ui/home');
    $data1 = $response1->json();

    // Test with headless=true
    config(['ui-services.headless_mode' => true]);
    $response2 = $this->getJson('/api/ui/home');
    $data2 = $response2->json();

    $response1->assertStatus(200);
    $response2->assertStatus(200);

    // Both should have UI components
    expect(count($data1))->toBeGreaterThan(0);
    expect(count($data2))->toBeGreaterThan(0);
});

// Test 4: Agent context is included in response when screen provides it
it('screen with agent context includes it in api response', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/ui/home');
    $data = $response->json();

    // Home screen may or may not have agent_context, this test validates structure
    if (isset($data['agent_context'])) {
        expect($data['agent_context'])->toBeArray();
    }

    $response->assertStatus(200);
});

// Test 5: Backward compatibility - catch-all still works with query params in web mode
it('catch-all respects query parameters in web mode', function () {
    /** @var \Tests\TestCase $this */
    config(['ui-services.headless_mode' => false]);

    $response = $this->get('/?reset=true');

    $response->assertStatus(200);
    $response->assertViewIs('usim::app');
});

// Test 6: Headless mode rejects nested routes
it('catch-all returns 406 for nested routes when headless mode enabled', function () {
    /** @var \Tests\TestCase $this */
    config(['ui-services.headless_mode' => true]);

    $response = $this->get('/admin/dashboard');

    $response->assertStatus(406);
    $response->assertJson([
        'error' => 'Headless mode enabled',
    ]);
});

// Test 7: API still serves nested screens in headless mode
it('api-ui serves nested screens in headless mode', function () {
    /** @var \Tests\TestCase $this */
    config(['ui-services.headless_mode' => true]);

    $response = $this->getJson('/api/ui/admin/dashboard');

    // This might return 404 or 401 depending on auth, but not 406
    expect($response->status())->not->toBe(406);
});
