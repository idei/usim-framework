<?php

use App\Models\User;
use App\Services\User\UserService;
use App\UI\Components\Modals\EditUserDialog;
use App\UI\Screens\Admin\TableModels\UserTableModel;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\UI;
use Spatie\Permission\Models\Role;

it('adapts table columns and role labels in Simple Mode (only system units)', function () {
    // Ensure only system units exist
    UsimUnit::whereNotIn('slug', ['main', 'lobby'])->delete();
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);

    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    // 1. Registered user in lobby
    $registeredUser = User::factory()->create([
        'name' => 'Lobby Waiting User',
        'email' => 'waiting.user@example.com',
    ]);
    $registeredUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $registeredUser->syncRoles(['registered']);

    // 2. Active user in main
    $activeUser = User::factory()->create([
        'name' => 'Active Main User',
        'email' => 'active.user@example.com',
    ]);
    $activeUser->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $activeUser->syncRoles(['member']);

    setPermissionsTeamId(null);

    $table = UI::table('users_table')->dataModel(UserTableModel::class);
    $model = new UserTableModel($table);

    // Columns check: 'units' column MUST NOT be present
    $columns = $model->getColumns();
    expect(array_keys($columns))->toBe(['name', 'email', 'email_verified', 'roles']);

    // Test Spanish translations
    app()->setLocale('es');
    $rowsEs = $model->getFormattedPageData(1, 50);
    $lobbyRowEs = collect($rowsEs)->firstWhere('email', 'waiting.user@example.com');
    expect($lobbyRowEs['roles'])->toBe('En espera (Registrado)');
    expect(isset($lobbyRowEs['units']))->toBeFalse();

    $activeRowEs = collect($rowsEs)->firstWhere('email', 'active.user@example.com');
    expect($activeRowEs['roles'])->toBe('Miembro');

    // Test English translations
    app()->setLocale('en');
    $rowsEn = $model->getFormattedPageData(1, 50);
    $lobbyRowEn = collect($rowsEn)->firstWhere('email', 'waiting.user@example.com');
    expect($lobbyRowEn['roles'])->toBe('Waiting (Registered)');
    expect(isset($lobbyRowEn['units']))->toBeFalse();
});

it('adapts table columns and units formatting in Multi-Unit Mode', function () {
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $oafaUnit = UsimUnit::firstOrCreate(['slug' => 'oafa'], ['type' => 'department']);

    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    // 1. User in lobby
    $lobbyUser = User::factory()->create([
        'name' => 'Multi Lobby User',
        'email' => 'multi.lobby@example.com',
    ]);
    $lobbyUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $lobbyUser->syncRoles(['registered']);

    // 2. User with single unit
    $singleUnitUser = User::factory()->create([
        'name' => 'Single Unit User',
        'email' => 'single.unit@example.com',
    ]);
    $singleUnitUser->usimUnits()->sync([$ideiUnit->id]);
    setPermissionsTeamId($ideiUnit->id);
    $singleUnitUser->syncRoles(['member']);

    // 3. User with multiple operational units
    $multiUnitUser = User::factory()->create([
        'name' => 'Multi Units User',
        'email' => 'multi.units@example.com',
    ]);
    $multiUnitUser->usimUnits()->sync([$ideiUnit->id, $oafaUnit->id]);
    setPermissionsTeamId($ideiUnit->id);
    $multiUnitUser->syncRoles(['member']);

    setPermissionsTeamId(null);

    $table = UI::table('users_table')->dataModel(UserTableModel::class);
    $model = new UserTableModel($table);

    // Columns check: 'units' column MUST be present
    $columns = $model->getColumns();
    expect(array_keys($columns))->toBe(['name', 'email', 'email_verified', 'units', 'roles']);

    // Test English
    app()->setLocale('en');
    $rowsEn = $model->getFormattedPageData(1, 50);

    $lobbyRowEn = collect($rowsEn)->firstWhere('email', 'multi.lobby@example.com');
    expect($lobbyRowEn['units'])->toBe('Lobby');
    expect($lobbyRowEn['roles'])->toBe('Registered User');

    $singleRowEn = collect($rowsEn)->firstWhere('email', 'single.unit@example.com');
    expect($singleRowEn['units'])->toBe('Institute of Informatics');
    expect($singleRowEn['roles'])->toBe('Member');

    $multiRowEn = collect($rowsEn)->firstWhere('email', 'multi.units@example.com');
    expect($multiRowEn['units'])->toBe('Institute of Informatics (+1)');

    // Test Spanish
    app()->setLocale('es');
    $rowsEs = $model->getFormattedPageData(1, 50);

    $lobbyRowEs = collect($rowsEs)->firstWhere('email', 'multi.lobby@example.com');
    expect($lobbyRowEs['units'])->toBe('Espera');
    expect($lobbyRowEs['roles'])->toBe('Usuario Registrado');
});

it('renders EditUserDialog appropriately for lobby user in Simple Mode', function () {
    UsimUnit::whereNotIn('slug', ['main', 'lobby'])->delete();
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    $lobbyUser = User::factory()->create([
        'name' => 'Pending Approval User',
        'email' => 'pending.user@example.com',
    ]);
    $lobbyUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $lobbyUser->syncRoles(['registered']);
    setPermissionsTeamId(null);

    $userService = app(UserService::class);
    $response = $userService->getUser($lobbyUser->id);
    expect($response['status'])->toBe('success');
    $userData = $response['data'];

    expect($userData['is_in_lobby'])->toBeTrue();
    expect($userData['has_operational_units'])->toBeFalse();

    app()->setLocale('es');
    $dialog = new EditUserDialog();
    $ui = $dialog->getUI(user: $userData);

    // Banner is present
    expect(findComponentByName($ui, 'lobby_banner'))->not->toBeNull();
    // In simple mode, target_unit select is NOT present
    expect(findComponentByName($ui, 'target_unit'))->toBeNull();
    // Submit button says 'Aprobar y Activar Usuario'
    $btn = findComponentByName($ui, 'btn_submit_register');
    expect($btn['label'])->toBe('Aprobar y Activar Usuario');

    // Test English button label
    app()->setLocale('en');
    $uiEn = $dialog->getUI(user: $userData);
    $btnEn = findComponentByName($uiEn, 'btn_submit_register');
    expect($btnEn['label'])->toBe('Approve & Activate User');
});

it('renders EditUserDialog appropriately for lobby user in Multi-Unit Mode', function () {
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
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

    $userService = app(UserService::class);
    $response = $userService->getUser($lobbyUser->id);
    expect($response['status'])->toBe('success');
    $userData = $response['data'];

    expect($userData['is_in_lobby'])->toBeTrue();
    expect($userData['has_operational_units'])->toBeTrue();
    // Active unit context set (e.g. from local storage store_unit)
    $userData['active_unit'] = [
        'id' => $ideiUnit->id,
        'slug' => $ideiUnit->slug,
        'name' => 'Instituto de Informática',
    ];

    app()->setLocale('es');
    $dialog = new EditUserDialog();
    $ui = $dialog->getUI(user: $userData);

    // Banner is present
    expect(findComponentByName($ui, 'lobby_banner'))->not->toBeNull();
    // In multi-unit mode, active_unit badge and help are present (no select dropdown)
    expect(findComponentByName($ui, 'active_unit_badge'))->not->toBeNull();
    expect(findComponentByName($ui, 'active_unit_help'))->not->toBeNull();
    $hiddenTarget = findComponentByName($ui, 'target_unit');
    expect($hiddenTarget)->not->toBeNull();
    expect($hiddenTarget['value'])->toBe((string) $ideiUnit->id);

    // Submit button says 'Aprobar en Instituto de Informática'
    $btn = findComponentByName($ui, 'btn_submit_register');
    expect($btn['label'])->toBe('Aprobar en Instituto de Informática');

    // Test English button label
    app()->setLocale('en');
    $userData['active_unit']['name'] = 'Institute of Informatics';
    $uiEn = $dialog->getUI(user: $userData);
    $btnEn = findComponentByName($uiEn, 'btn_submit_register');
    expect($btnEn['label'])->toBe('Approve in Institute of Informatics');
});

it('successfully approves a lobby user into main in Simple Mode', function () {
    UsimUnit::whereNotIn('slug', ['main', 'lobby'])->delete();
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create([
        'name' => 'To Approve Simple',
        'email' => 'approve.simple@example.com',
    ]);
    $user->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $user->syncRoles(['registered']);
    setPermissionsTeamId(null);

    $userService = app(UserService::class);
    $result = $userService->updateUser($user, [
        'name' => 'Approved User Simple',
        'roles' => ['member'],
    ]);

    expect($result['status'])->toBe('success');

    $freshUser = $user->fresh();
    // Detached from lobby
    expect($freshUser->usimUnits->contains('slug', 'lobby'))->toBeFalse();
    // Attached to main
    expect($freshUser->usimUnits->contains('slug', 'main'))->toBeTrue();

    // In main unit context, has role member and no longer registered
    setPermissionsTeamId($mainUnit->id);
    expect($freshUser->hasRole('member'))->toBeTrue();
    expect($freshUser->hasRole('registered'))->toBeFalse();
    setPermissionsTeamId(null);
});

it('successfully approves a lobby user into chosen operational unit in Multi-Unit Mode', function () {
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'name' => 'To Approve Multi',
        'email' => 'approve.multi@example.com',
    ]);
    $user->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $user->syncRoles(['registered']);
    setPermissionsTeamId(null);

    $userService = app(UserService::class);
    $result = $userService->updateUser($user, [
        'name' => 'Approved User Multi',
        'target_unit' => $ideiUnit->id,
        'roles' => ['admin'],
    ]);

    expect($result['status'])->toBe('success');

    $freshUser = $user->fresh();
    // Detached from lobby
    expect($freshUser->usimUnits->contains('slug', 'lobby'))->toBeFalse();
    // Attached to idei
    expect($freshUser->usimUnits->contains('slug', 'idei'))->toBeTrue();

    // In idei context, has role admin and no longer registered
    setPermissionsTeamId($ideiUnit->id);
    expect($freshUser->hasRole('admin'))->toBeTrue();
    expect($freshUser->hasRole('registered'))->toBeFalse();
    setPermissionsTeamId(null);
});

it('resolves the operational unit selected in store_unit for root user in UsersManager', function () {
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    Role::findOrCreate('root', 'web');

    $rootUser = User::factory()->create([
        'name' => 'Root Admin',
        'email' => 'root.test@example.com',
    ]);
    $rootUser->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $rootUser->syncRoles(['root']);
    setPermissionsTeamId(null);

    $this->actingAs($rootUser);

    // UnitContextResolver::resolve directly for root with operational unit slug
    $resolved = Idei\Usim\Support\UnitContextResolver::resolve($rootUser, 'idei');
    expect($resolved)->not->toBeNull();
    expect($resolved->slug)->toBe('idei');

    // Inside UsersManager screen context with injected store_unit
    $manager = app(App\UI\Screens\Admin\UsersManager::class);
    $reflection = new ReflectionClass($manager);
    $prop = $reflection->getProperty('store_unit');
    $prop->setAccessible(true);
    $prop->setValue($manager, 'idei');

    $resolveMethod = $reflection->getMethod('resolveActiveUnit');
    $resolveMethod->setAccessible(true);
    /** @var Idei\Usim\Models\UsimUnit $activeUnit */
    $activeUnit = $resolveMethod->invoke($manager);

    expect($activeUnit)->not->toBeNull();
    expect($activeUnit->slug)->toBe('idei');
    expect($activeUnit->slug)->not->toBe('main');
});


