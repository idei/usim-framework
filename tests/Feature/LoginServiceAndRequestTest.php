<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('validates login request data using LoginRequest::validateData', function () {
    // Valid data
    $validated = LoginRequest::validateData([
        'login_email' => 'user@example.com',
        'login_password' => 'secret123',
        'remember' => true,
    ]);

    expect($validated['email'])->toBe('user@example.com')
        ->and($validated['password'])->toBe('secret123')
        ->and($validated['remember'])->toBeTrue();

    // Invalid email triggers ValidationException
    expect(fn () => LoginRequest::validateData([
        'login_email' => 'not-an-email',
        'login_password' => 'secret123',
    ]))->toThrow(ValidationException::class);

    // Missing password triggers ValidationException
    expect(fn () => LoginRequest::validateData([
        'login_email' => 'user@example.com',
        'login_password' => '',
    ]))->toThrow(ValidationException::class);
});

it('returns units, active_unit, home_screen, and redirect_to from LoginService::login', function () {
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $roleAdmin = Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'email' => 'service-test@example.com',
        'password' => bcrypt('password123'),
    ]);
    $user->usimUnits()->sync([$mainUnit->id]);

    setPermissionsTeamId($mainUnit->id);
    $user->assignRole($roleAdmin);
    setPermissionsTeamId(null);

    /** @var LoginService $loginService */
    $loginService = app(LoginService::class);

    $response = $loginService->login('service-test@example.com', 'password123');

    expect($response['status'])->toBe('success')
        ->and($response['units'])->toHaveKey('main')
        ->and($response['units']['main'])->toContain('admin')
        ->and($response['active_unit'])->toBe('main')
        ->and($response['home_screen'])->toBe(UsersManager::class)
        ->and($response['redirect_to'])->toBe(UsersManager::getRoutePath())
        ->and($response['data']['units'])->toHaveKey('main')
        ->and($response['data']['active_unit'])->toBe('main')
        ->and($response['data']['home_screen'])->toBe(UsersManager::class)
        ->and($response['data']['redirect_to'])->toBe(UsersManager::getRoutePath());

    // By default (startSession: false), user is not logged in session
    expect(auth()->check())->toBeFalse();
});

it('establishes session when LoginService::login is called with startSession: true', function () {
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $roleAdmin = Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'email' => 'session-login@example.com',
        'password' => bcrypt('password123'),
    ]);
    $user->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $user->assignRole($roleAdmin);
    setPermissionsTeamId(null);

    /** @var LoginService $loginService */
    $loginService = app(LoginService::class);

    $response = $loginService->login('session-login@example.com', 'password123', startSession: true);

    expect($response['status'])->toBe('success');
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
    expect(\Idei\Usim\Support\UIStateManager::getAuthToken())->toBe($response['token']);
});

it('returns 422 when API login fails FormRequest validation', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'invalid-email',
        'password' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email', 'password']);
});

it('returns enriched payload with units and home_screen on successful API login', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'api-success@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'api-success@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'user',
            'token',
            'remember',
            'units',
            'active_unit',
            'home_screen',
            'redirect_to',
        ],
    ]);
});

it('handles validation error gracefully on UI Login screen', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'not-an-email',
            'login_password' => '',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull()
        ->and($response->json('toast.type'))->toBe('error')
        ->and($response->json('toast.message'))->toBe(t('service.auth.login.validation_errors'));
});
