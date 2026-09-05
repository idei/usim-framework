<?php

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\UI\Screens\Registered;
use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('loads registered screen with expected components and message for registered user', function () {
    Role::findOrCreate('registered');
    Permission::findOrCreate('home.access');
    $unit = UsimUnit::firstOrCreate(['slug' => 'lobby']);
    if (config('permission.teams')) {
        setPermissionsTeamId($unit->id);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create([
        'name' => 'Juan Perez',
        'email' => 'juan.perez@example.com',
    ]);
    $user->assignRole('registered');
    $this->actingAs($user);

    app()->setLocale('es');
    $appName = (string) config('usim.app_name', config('app.name', 'USIM Framework'));

    $ui = uiScenario($this, Registered::class, ['reset' => true]);

    $messageComponent = $ui->component('registered_message');
    expect($messageComponent->expect('type')->toBe('label'))->not->toBeNull();

    $messageText = (string) ($messageComponent->data()['text'] ?? '');
    expect($messageText)->toContain($appName);
    expect(mb_strtolower($messageText))->toContain('ha sido satisfactoriamente registrado en el sistema');
    expect(mb_strtolower($messageText))->toContain('pronto será asignado a una unidad con un rol');

    $cardComponent = $ui->component('registered_card');
    expect($cardComponent->data()['description'] ?? '')->toContain($appName);

    $titleComponent = $ui->component('registered_title');
    expect($titleComponent->data()['text'] ?? '')->not->toBeEmpty();

    $greetingComponent = $ui->component('registered_greeting');
    expect($greetingComponent->data()['text'] ?? '')->toContain('Juan Perez');

    $ui->assertNoIssues();
});

it('redirects unauthenticated guest accessing registered screen', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(screenApiUrl(Registered::class));
    $response->assertOk();
    expect($response->json('redirect'))->toBe(url('/auth/login'));
});

it('handles onGoToProfile action and redirects to profile', function () {
    Role::findOrCreate('registered');
    Permission::findOrCreate('home.access');
    $unit = UsimUnit::firstOrCreate(['slug' => 'lobby']);
    if (config('permission.teams')) {
        setPermissionsTeamId($unit->id);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create(['name' => 'Go Profile User']);
    $user->assignRole('registered');
    $this->actingAs($user);

    $ui = uiScenario($this, Registered::class, ['reset' => true]);

    $response = $ui->action('registered_card', 'go_to_profile');
    $response->assertOk();
    expect($response->json('redirect'))->toBe('/auth/profile');
});

it('renders registered screen message in Spanish', function () {
    Role::findOrCreate('registered');
    Permission::findOrCreate('home.access');
    $unit = UsimUnit::firstOrCreate(['slug' => 'lobby']);
    if (config('permission.teams')) {
        setPermissionsTeamId($unit->id);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create(['name' => 'Locale Tester ES']);
    $user->assignRole('registered');
    $this->actingAs($user);

    app()->setLocale('es');
    $appName = (string) config('usim.app_name', config('app.name', 'USIM Framework'));

    $ui = uiScenario($this, Registered::class, ['reset' => true]);
    $text = (string) ($ui->component('registered_message')->data()['text'] ?? '');
    expect($text)->toBe("Ha sido satisfactoriamente registrado en el sistema {$appName} y pronto será asignado a una unidad con un rol.");
});

it('renders registered screen message in English', function () {
    Role::findOrCreate('registered');
    Permission::findOrCreate('home.access');
    $unit = UsimUnit::firstOrCreate(['slug' => 'lobby']);
    if (config('permission.teams')) {
        setPermissionsTeamId($unit->id);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create(['name' => 'Locale Tester EN']);
    $user->assignRole('registered');
    $this->actingAs($user);

    app()->setLocale('en');
    $appName = (string) config('usim.app_name', config('app.name', 'USIM Framework'));

    $ui = uiScenario($this, Registered::class, ['reset' => true]);
    $text = (string) ($ui->component('registered_message')->data()['text'] ?? '');
    expect($text)->toBe("You have been successfully registered in the system {$appName} and will soon be assigned to a unit with a role.");
});

it('resolves post login redirect to registered screen route for registered user', function () {
    Role::findOrCreate('registered');
    $unit = UsimUnit::firstOrCreate(['slug' => 'lobby']);
    if (config('permission.teams')) {
        setPermissionsTeamId($unit->id);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var User $user */
    $user = User::factory()->create(['name' => 'Post Login User']);
    $user->assignRole('registered');

    $sessionService = app(AuthSessionService::class);
    $redirectUrl = $sessionService->resolvePostLoginRedirect($user);

    expect($redirectUrl)->toBe('/registered');
    expect(config('usim.roles.registered.home_screen'))->toBe(Registered::class);
});

