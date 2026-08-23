<?php

use App\Services\Role\RoleService;
use Idei\Usim\Models\UsimRole;
use Spatie\Permission\Models\Permission;

it('returns all permission IDs associated with a role by model instance, id and name', function () {
    $role = UsimRole::createWithHome('test_perm_role', 'test_home', 20);

    $perm1 = Permission::findOrCreate('perm_role_test_1');
    $perm2 = Permission::findOrCreate('perm_role_test_2');
    $perm3 = Permission::findOrCreate('perm_role_test_3');

    $role->syncPermissions([$perm1, $perm3]);

    $service = app(RoleService::class);

    // 1. By UsimRole model instance
    $idsByModel = $service->getPermissionIds($role);
    sort($idsByModel);
    $expectedIds = [$perm1->id, $perm3->id];
    sort($expectedIds);
    expect($idsByModel)->toBe($expectedIds);

    // 2. By integer ID
    $idsById = $service->getPermissionIds((int) $role->id);
    sort($idsById);
    expect($idsById)->toBe($expectedIds);

    // 3. By numeric string ID
    $idsByStringId = $service->getPermissionIds((string) $role->id);
    sort($idsByStringId);
    expect($idsByStringId)->toBe($expectedIds);

    // 4. By role name
    $idsByName = $service->getPermissionIds('test_perm_role');
    sort($idsByName);
    expect($idsByName)->toBe($expectedIds);

    // 5. When role has eager-loaded permissions relation
    $roleLoaded = UsimRole::with('permissions')->find($role->id);
    $idsLoaded = $service->getPermissionIds($roleLoaded);
    sort($idsLoaded);
    expect($idsLoaded)->toBe($expectedIds);

    // 6. Non-existent role returns empty array
    expect($service->getPermissionIds(999999))->toBe([]);
    expect($service->getPermissionIds('non_existent_role_xyz'))->toBe([]);

    // 7. Role with no permissions returns empty array
    $emptyRole = UsimRole::createWithHome('empty_perm_role', 'empty_home', 30);
    expect($service->getPermissionIds($emptyRole))->toBe([]);
});

it('toggles permission for a role attaching and detaching it correctly', function () {
    $role = UsimRole::createWithHome('test_toggle_role', 'test_home', 10);
    $perm = Permission::findOrCreate('perm_toggle_test');

    $service = app(RoleService::class);

    // Initially role does not have the permission
    expect($service->getPermissionIds($role))->not->toContain($perm->id);

    // 1. Toggle ON by ID -> attaches permission and returns true
    $result1 = $service->togglePermission((int) $role->id, (int) $perm->id);
    expect($result1)->toBeTrue();
    expect($service->getPermissionIds($role))->toContain($perm->id);

    // 2. Toggle OFF by string name -> detaches permission and returns false
    $result2 = $service->togglePermission('test_toggle_role', 'perm_toggle_test');
    expect($result2)->toBeFalse();
    expect($service->getPermissionIds($role))->not->toContain($perm->id);

    // 3. Toggle ON by model instances -> attaches permission and returns true
    $result3 = $service->togglePermission($role, $perm);
    expect($result3)->toBeTrue();
    expect($service->getPermissionIds($role))->toContain($perm->id);

    // 4. Returns false if role or permission not found
    expect($service->togglePermission(999999, (int) $perm->id))->toBeFalse();
    expect($service->togglePermission((int) $role->id, 999999))->toBeFalse();
});

it('adds and removes permissions explicitly for a role', function () {
    $role = UsimRole::createWithHome('test_explicit_role', 'test_home', 15);
    $perm = Permission::findOrCreate('perm_explicit_test');

    $service = app(RoleService::class);

    // Add permission
    $added = $service->addPermission($role, $perm);
    expect($added)->toBeTrue();
    expect($service->getPermissionIds($role))->toContain($perm->id);

    // Remove permission
    $removed = $service->removePermission($role, $perm);
    expect($removed)->toBeTrue();
    expect($service->getPermissionIds($role))->not->toContain($perm->id);

    // Non-existent role or permission returns false
    expect($service->addPermission(999999, $perm))->toBeFalse();
    expect($service->removePermission(999999, $perm))->toBeFalse();
});
