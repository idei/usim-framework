<?php

use App\Models\User;

function serviceRootComponentId(array $payload): int
{
    foreach ($payload as $id => $component) {
        if (!is_array($component)) {
            continue;
        }

        if (($component['type'] ?? null) === 'container' && is_numeric($id)) {
            return (int) $id;
        }
    }

    throw new RuntimeException('Service root component id not found in UI payload.');
}

it('returns redirect contract on successful login event', function () {
    /** @var \Tests\TestCase $this */
    $password = 'secret123';
    User::factory()->create([
        'email' => 'ui-login@example.com',
        'password' => bcrypt($password),
    ]);

    $uiResponse = $this->getJson('/api/ui/auth/login');
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-login@example.com',
            'login_password' => $password,
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
});

it('returns non-redirect UI feedback contract for invalid login event', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create([
        'email' => 'ui-invalid@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $uiResponse = $this->getJson('/api/ui/auth/login');
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-invalid@example.com',
            'login_password' => 'wrong-password',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
    expect($response->json('toast'))->toBeArray();
});

it('returns UI feedback when forgot-password is submitted without email', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = $this->getJson('/api/ui/auth/forgot-password');
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'send_link',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();
});

it('returns UI error feedback when reset-password has mismatched confirmation', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = $this->getJson('/api/ui/auth/reset-password');
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'reset_password',
        'parameters' => [
            'reset_token' => 'dummy-token',
            'reset_email' => 'user@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different123',
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast'))->toBeArray();
    expect($response->json('redirect'))->toBeNull();
});

it('returns redirect contract on confirm_logout event from menu screen', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    $uiResponse = $this->getJson('/api/ui/menu?parent=menu');
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'confirm_logout',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
});
