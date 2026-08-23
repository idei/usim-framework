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
