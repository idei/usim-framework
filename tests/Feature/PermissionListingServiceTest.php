<?php

use App\Services\Permissions\PermissionListingService;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Models\UsimRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;


it('filters permissions by role_id and does not filter when role_id is null', function () {
    $roleA = Role::findOrCreate('role_a_test');
    $roleB = Role::findOrCreate('role_b_test');

    $perm1 = Permission::findOrCreate('perm_1_test');
    $perm2 = Permission::findOrCreate('perm_2_test');
    $perm3 = Permission::findOrCreate('perm_3_test');

    $roleA->syncPermissions([$perm1, $perm2]);
    $roleB->syncPermissions([$perm2, $perm3]);

    $service = app(PermissionListingService::class);

    // Filter by roleA
    $filteredA = $service->filter(['role_id' => $roleA->id]);
    $namesA = array_map(fn (Permission $p) => $p->name, $filteredA);
    sort($namesA);
    expect($namesA)->toBe(['perm_1_test', 'perm_2_test']);

    // Filter by roleB
    $filteredB = $service->filter(['role_id' => $roleB->id]);
    $namesB = array_map(fn (Permission $p) => $p->name, $filteredB);
    sort($namesB);
    expect($namesB)->toBe(['perm_2_test', 'perm_3_test']);

    // When role_id is null, it does not filter anything
    $allWithoutFilter = $service->filter(['role_id' => null]);
    $allNames = array_map(fn (Permission $p) => $p->name, $allWithoutFilter);
    expect($allNames)->toContain('perm_1_test', 'perm_2_test', 'perm_3_test');

    // Paginate with role_id filter
    $paginatedA = $service->paginate(page: 1, perPage: 10, filters: ['role_id' => $roleA->id]);
    expect($paginatedA['total'])->toBe(2);
    $paginatedNamesA = array_map(fn (Permission $p) => $p->name, $paginatedA['items']);
    sort($paginatedNamesA);
    expect($paginatedNamesA)->toBe(['perm_1_test', 'perm_2_test']);

    // Paginate with role_id = null
    $paginatedNull = $service->paginate(page: 1, perPage: 50, filters: ['role_id' => null]);
    $paginatedNullNames = array_map(fn (Permission $p) => $p->name, $paginatedNull['items']);
    expect($paginatedNullNames)->toContain('perm_1_test', 'perm_2_test', 'perm_3_test');

    // Count matching
    expect($service->countMatching(filters: ['role_id' => $roleA->id]))->toBe(2);
    expect($service->countMatching(filters: ['role_id' => null]))->toBeGreaterThanOrEqual(3);
});

it('correctly sorts permissions by name in PermissionListingService', function () {
    Permission::findOrCreate('a_perm_sort_test');
    Permission::findOrCreate('m_perm_sort_test');
    Permission::findOrCreate('z_perm_sort_test');

    $service = app(PermissionListingService::class);

    // ASC
    $ascResult = $service->paginate(page: 1, perPage: 10, search: '_perm_sort_test', sortField: 'name', sortDirection: 'asc');
    $namesAsc = array_map(fn (Permission $p) => $p->name, $ascResult['items']);
    expect($namesAsc)->toBe(['a_perm_sort_test', 'm_perm_sort_test', 'z_perm_sort_test']);

    // DESC
    $descResult = $service->paginate(page: 1, perPage: 10, search: '_perm_sort_test', sortField: 'name', sortDirection: 'desc');
    $namesDesc = array_map(fn (Permission $p) => $p->name, $descResult['items']);
    expect($namesDesc)->toBe(['z_perm_sort_test', 'm_perm_sort_test', 'a_perm_sort_test']);
});

it('handles permissions table column clicked sorting in UsersManager screen', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('permissions_table', 'permissions_table_column_clicked', [
        'sort_by' => 'name',
        'column_text' => 'Name',
    ]);
    $response->assertOk();
    expect($response->json('error'))->toBeNull();
    $ui->component('permissions_table')->expect('sort_column')->toBe('name');

    $ui->assertNoIssues();
});

it('toggles permission row selection on permissions_table_row_clicked in UsersManager screen', function () {
    UsimRole::findOrCreate('root');
    $role = UsimRole::createWithHome('custom_test_role', 'custom_home', 25);
    $perm1 = Permission::findOrCreate('custom_perm_test_1');
    $perm2 = Permission::findOrCreate('custom_perm_test_2');

    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // 1. Without role selected -> shows warning toast
    $resNoRole = $ui->action('permissions_table', 'permissions_table_row_clicked', [
        'model_id' => $perm1->id,
    ]);
    $resNoRole->assertOk();
    expect($resNoRole->json('toast.type'))->toBe('warning');
    expect($role->fresh()->hasPermissionTo($perm1))->toBeFalse();

    // 2. Select role in roles_table
    $resRole = $ui->action('roles_table', 'roles_table_row_clicked', [
        'model_id' => $role->id,
    ]);
    $resRole->assertOk();

    // 3. Click permission row with perm1 -> becomes attached in DB and selected in UI
    $res1 = $ui->action('permissions_table', 'permissions_table_row_clicked', [
        'model_id' => $perm1->id,
    ]);
    $res1->assertOk();
    expect($role->fresh()->hasPermissionTo($perm1))->toBeTrue();
    $ui->component('permissions_table')->expect('selected_rows')->toContain($perm1->id);

    // 4. Click permission row with perm2 -> both perm1 and perm2 are attached in DB and selected in UI
    $res2 = $ui->action('permissions_table', 'permissions_table_row_clicked', [
        'model_id' => $perm2->id,
    ]);
    $res2->assertOk();
    expect($role->fresh()->hasPermissionTo($perm2))->toBeTrue();
    $ui->component('permissions_table')->expect('selected_rows')->toContain($perm1->id);
    $ui->component('permissions_table')->expect('selected_rows')->toContain($perm2->id);

    // 5. Click permission row with perm1 again -> perm1 is detached in DB and unselected in UI, perm2 remains
    $res3 = $ui->action('permissions_table', 'permissions_table_row_clicked', [
        'model_id' => $perm1->id,
    ]);
    $res3->assertOk();
    expect($role->fresh()->hasPermissionTo($perm1))->toBeFalse();
    expect($role->fresh()->hasPermissionTo($perm2))->toBeTrue();
    $ui->component('permissions_table')->expect('selected_rows')->not->toContain($perm1->id);
    $ui->component('permissions_table')->expect('selected_rows')->toContain($perm2->id);

    $ui->assertNoIssues();
});

