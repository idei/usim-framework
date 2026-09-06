<?php

use App\Models\User;
use App\UI\Screens\Admin\TableModels\UserTableModel;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\UI;
use Spatie\Permission\Models\Role;

it('formats user roles correctly using global roles in multi-unit context and avoids raw role.none', function () {
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    Role::findOrCreate('registered', 'web');
    Role::findOrCreate('member', 'web');

    // 1. Registered user in lobby
    $registeredUser = User::factory()->create([
        'name' => 'Registered Example',
        'email' => 'registered.example@example.com',
    ]);
    $registeredUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $registeredUser->syncRoles(['registered']);

    // 2. Operational member user in idei
    $memberUser = User::factory()->create([
        'name' => 'Member Example',
        'email' => 'member.example@example.com',
    ]);
    $memberUser->usimUnits()->sync([$ideiUnit->id]);
    setPermissionsTeamId($ideiUnit->id);
    $memberUser->syncRoles(['member']);

    // 3. User with no roles
    $noRoleUser = User::factory()->create([
        'name' => 'No Role Example',
        'email' => 'norole.example@example.com',
    ]);

    // Simulate an admin browsing from another unit (e.g., main or oafa)
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    setPermissionsTeamId($mainUnit->id);

    $table = UI::table('users_table')->dataModel(UserTableModel::class);
    $model = new UserTableModel($table);

    // Test English
    app()->setLocale('en');
    $formattedEn = $model->getFormattedPageData(1, 50);

    $regRowEn = collect($formattedEn)->firstWhere('email', 'registered.example@example.com');
    expect($regRowEn)->not->toBeNull();
    expect($regRowEn['roles'])->toBe('Registered User');

    $memRowEn = collect($formattedEn)->firstWhere('email', 'member.example@example.com');
    expect($memRowEn)->not->toBeNull();
    expect($memRowEn['roles'])->toBe('Member');

    $noRoleRowEn = collect($formattedEn)->firstWhere('email', 'norole.example@example.com');
    expect($noRoleRowEn)->not->toBeNull();
    expect($noRoleRowEn['roles'])->toBe('None');

    // Test Spanish
    app()->setLocale('es');
    $formattedEs = $model->getFormattedPageData(1, 50);

    $regRowEs = collect($formattedEs)->firstWhere('email', 'registered.example@example.com');
    expect($regRowEs)->not->toBeNull();
    expect($regRowEs['roles'])->toBe('Usuario Registrado');

    $memRowEs = collect($formattedEs)->firstWhere('email', 'member.example@example.com');
    expect($memRowEs)->not->toBeNull();
    expect($memRowEs['roles'])->toBe('Miembro');

    $noRoleRowEs = collect($formattedEs)->firstWhere('email', 'norole.example@example.com');
    expect($noRoleRowEs)->not->toBeNull();
    expect($noRoleRowEs['roles'])->toBe('Ninguno');

    setPermissionsTeamId(null);
});

