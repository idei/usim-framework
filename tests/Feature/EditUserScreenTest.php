<?php

use App\Models\User;
use App\UI\Screens\Admin\EditUser;
use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
});

it('loads edit user screen with expected base components', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Alice Doe',
        'email' => 'alice@example.com',
    ]);
    $this->actingAs($user);

    $uiResponse = getScreenJson($this, EditUser::class, ['user_id' => $user->id]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $dialog = findComponentByName($payload, 'edit_user_dialog');
    $nameInput = findComponentByName($payload, 'name');
    $emailInput = findComponentByName($payload, 'email');
    $userIdInput = findComponentByName($payload, 'user_id');
    $rolesCheckbox = findComponentByName($payload, 'roles');
    $sendResetEmail = findComponentByName($payload, 'send_reset_email');
    $cancelBtn = findComponentByName($payload, 'btn_cancel_register');
    $submitBtn = findComponentByName($payload, 'btn_submit_register');
    $deleteBtn = findComponentByName($payload, 'btn_delete_user');

    expect($dialog)->not->toBeNull()
        ->and($nameInput)->not->toBeNull()
        ->and($nameInput['type'])->toBe('input')
        ->and($nameInput['value'])->toBe('Alice Doe')
        ->and($emailInput)->not->toBeNull()
        ->and($emailInput['type'])->toBe('input')
        ->and($emailInput['value'])->toBe('alice@example.com')
        ->and($userIdInput)->not->toBeNull()
        ->and($userIdInput['type'])->toBe('input')
        ->and($userIdInput['value'])->toBe((string) $user->id)
        ->and($rolesCheckbox)->not->toBeNull()
        ->and($rolesCheckbox['type'])->toBe('checkbox')
        ->and($sendResetEmail)->not->toBeNull()
        ->and($sendResetEmail['type'])->toBe('checkbox')
        ->and($cancelBtn)->not->toBeNull()
        ->and($cancelBtn['type'])->toBe('button')
        ->and($cancelBtn['action'])->toBe('close_edit_user')
        ->and($submitBtn)->not->toBeNull()
        ->and($submitBtn['type'])->toBe('button')
        ->and($submitBtn['action'])->toBe('submit_update_user')
        ->and($deleteBtn)->not->toBeNull()
        ->and($deleteBtn['type'])->toBe('button')
        ->and($deleteBtn['action'])->toBe('delete_user');
});

it('renders edit user screen appropriately for lobby user in Simple Mode', function () {
    /** @var \Tests\TestCase $this */
    UsimUnit::whereNotIn('slug', ['main', 'lobby'])->delete();
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    $lobbyUser = User::factory()->create([
        'name' => 'Pending Lobby User',
        'email' => 'pending.lobby@example.com',
    ]);
    $lobbyUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $lobbyUser->syncRoles(['registered']);
    setPermissionsTeamId(null);

    $this->actingAs($lobbyUser);

    app()->setLocale('es');
    $uiResponse = getScreenJson($this, EditUser::class, ['user_id' => $lobbyUser->id]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    expect(findComponentByName($payload, 'lobby_banner'))->not->toBeNull();
    expect(findComponentByName($payload, 'target_unit'))->toBeNull();

    $submitBtn = findComponentByName($payload, 'btn_submit_register');
    expect($submitBtn['label'])->toBe('Aprobar y Activar Usuario');
});

it('renders edit user screen appropriately for lobby user in Multi-Unit Mode', function () {
    /** @var \Tests\TestCase $this */
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute', 'display_name' => 'Instituto de Informática']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    $lobbyUser = User::factory()->create([
        'name' => 'Pending Multi User',
        'email' => 'pending.multi@example.com',
    ]);
    $lobbyUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $lobbyUser->syncRoles(['registered']);
    setPermissionsTeamId(null);

    $this->actingAs($lobbyUser);

    app()->setLocale('es');
    $userParam = [
        'id' => $lobbyUser->id,
        'name' => $lobbyUser->name,
        'email' => $lobbyUser->email,
        'is_in_lobby' => true,
        'has_operational_units' => true,
        'active_unit' => [
            'id' => $ideiUnit->id,
            'slug' => $ideiUnit->slug,
            'name' => 'Instituto de Informática',
        ],
    ];

    $uiResponse = getScreenJson($this, EditUser::class, ['user' => $userParam]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    expect(findComponentByName($payload, 'lobby_banner'))->not->toBeNull();
    expect(findComponentByName($payload, 'active_unit_badge'))->not->toBeNull();
    expect(findComponentByName($payload, 'active_unit_help'))->not->toBeNull();

    $hiddenTarget = findComponentByName($payload, 'target_unit');
    expect($hiddenTarget)->not->toBeNull()
        ->and($hiddenTarget['value'])->toBe((string) $ideiUnit->id);

    $submitBtn = findComponentByName($payload, 'btn_submit_register');
    expect($submitBtn['label'])->toBe('Aprobar en Instituto de Informática');
});

it('updates user successfully and redirects to home when standalone', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $this->actingAs($user);

    $uiResponse = getScreenJson($this, EditUser::class, ['user_id' => $user->id]);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_update_user',
        'parameters' => [
            'user_id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'roles' => ['member'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

it('handles cancel by redirecting to home when not opened as modal', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $uiResponse = getScreenJson($this, EditUser::class, ['user_id' => $user->id]);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'close_edit_user',
        'parameters' => [],
    ]);

    $response->assertOk();
});

it('opens edit user as modal and handles close modal action', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Modal User',
        'email' => 'modal.user@example.com',
    ]);
    $this->actingAs($user);

    $modal = EditUser::openAsModal(['user_id' => $user->id]);
    expect($modal->isOpenedAsModal())->toBeTrue();
    $changes = app(\Idei\Usim\UIChangesCollector::class)->all();
    $componentId = serviceRootComponentId($changes);

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'close_edit_user',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
});
