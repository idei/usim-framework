<?php

use App\Models\User;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Services\UsimUnitsService;

it('returns empty array when user has no units or roles', function () {
    $user = User::factory()->create();
    $service = new UsimUnitsService();

    $result = $service->getUserUnitsWithRoles($user);

    expect($result)->toBe([]);
});

it('returns units with empty role list when user has membership but no roles assigned', function () {
    $user = User::factory()->create();
    $unit = UsimUnit::firstOrCreate(['slug' => 'sales']);
    $user->usimUnits()->sync([$unit->id]);

    $service = new UsimUnitsService();
    $result = $service->getUserUnitsWithRoles($user);

    expect($result)->toHaveKey('sales');
    expect($result['sales'])->toBe([]);
});

it('returns units with their assigned roles for the user', function () {
    $user = User::factory()->create();
    $unitA = UsimUnit::firstOrCreate(['slug' => 'hq']);
    $unitB = UsimUnit::firstOrCreate(['slug' => 'branch']);

    $user->usimUnits()->sync([$unitA->id, $unitB->id]);

    $roleAdmin = UsimRole::findOrCreate('admin', 'web');
    $roleEditor = UsimRole::findOrCreate('editor', 'web');
    $roleViewer = UsimRole::findOrCreate('viewer', 'web');

    // Assign roles in unitA
    setPermissionsTeamId($unitA->id);
    $user->assignRole($roleAdmin, $roleEditor);

    // Assign roles in unitB
    setPermissionsTeamId($unitB->id);
    $user->assignRole($roleViewer);

    $service = new UsimUnitsService();
    $result = $service->getUserUnitsWithRoles($user);

    expect($result)->toHaveKeys(['hq', 'branch']);
    expect($result['hq'])->toContain('admin', 'editor');
    expect($result['hq'])->not->toContain('viewer');
    expect($result['branch'])->toBe(['viewer']);
});
