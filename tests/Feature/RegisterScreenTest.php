<?php

use App\Models\User;
use App\UI\Screens\Auth\Register;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('loads register screen with expected base components', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Register::class);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $name = findComponentByName($payload, 'name');
    $email = findComponentByName($payload, 'email');
    $password = findComponentByName($payload, 'password');
    $passwordConfirmation = findComponentByName($payload, 'password_confirmation');
    $acceptTerms = findComponentByName($payload, 'accept_terms');
    $submitBtn = findComponentByName($payload, 'btn_submit_register');
    $cancelBtn = findComponentByName($payload, 'btn_cancel_register');
    $loginBtn = findComponentByName($payload, 'btn_to_login');

    expect($name)->not->toBeNull()
        ->and($name['type'])->toBe('input')
        ->and($email)->not->toBeNull()
        ->and($email['type'])->toBe('input')
        ->and($password)->not->toBeNull()
        ->and($password['type'])->toBe('input')
        ->and($passwordConfirmation)->not->toBeNull()
        ->and($passwordConfirmation['type'])->toBe('input')
        ->and($acceptTerms)->not->toBeNull()
        ->and($acceptTerms['type'])->toBe('checkbox')
        ->and($submitBtn)->not->toBeNull()
        ->and($submitBtn['action'])->toBe('submit_register')
        ->and($cancelBtn)->not->toBeNull()
        ->and($cancelBtn['action'])->toBe('close_register_dialog');
});

it('shows error when terms are not accepted', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Register::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_register',
        'parameters' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accept_terms' => false,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast'))->toBeArray()
        ->and($response->json('toast.type'))->toBe('error')
        ->and($response->json('redirect'))->toBeNull();
});

it('shows validation error when passwords do not match', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Register::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_register',
        'parameters' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'different_password',
            'accept_terms' => true,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast'))->toBeArray()
        ->and($response->json('toast.type'))->toBe('error')
        ->and($response->json('redirect'))->toBeNull();
});

it('registers user successfully, establishes session and redirects', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Register::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $email = 'newuser_' . uniqid() . '@example.com';

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_register',
        'parameters' => [
            'name' => 'New User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

it('handles cancel by redirecting to home when not a modal', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Register::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'close_register_dialog',
        'parameters' => [],
    ]);

    $response->assertOk();
});
