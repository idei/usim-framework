<?php

use App\Services\Permissions\PermissionListingService;
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

it('renders roles column at the start of PermissionTableModel', function () {
    $role1 = Role::findOrCreate('role_table_test_1');
    $role2 = Role::findOrCreate('role_table_test_2');
    $perm = Permission::findOrCreate('perm_table_test_multi');
    $role1->syncPermissions([$perm]);
    $role2->syncPermissions([$perm]);

    $table = \Idei\Usim\UI::table('permissions_table');
    $model = new \App\UI\Screens\Admin\TableModels\PermissionTableModel($table);

    $columns = $model->getColumns();
    $columnKeys = array_keys($columns);
    expect($columnKeys[0])->toBe('roles');
    expect($columnKeys[1])->toBe('name');

    $formatted = $model->getFormattedPageData(1, 50);
    $found = null;
    foreach ($formatted as $row) {
        if ($row['_model_id'] === $perm->id) {
            $found = $row;
            break;
        }
    }

    expect($found)->not->toBeNull();
    expect($found['roles'])->toContain((string) $role1->id);
    expect($found['roles'])->toContain((string) $role2->id);
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
    \Idei\Usim\Models\UsimRole::findOrCreate('root');

    $root = \App\Models\User::factory()->create();
    $root->assignRole('root');

    $this->actingAs($root);

    $ui = uiScenario($this, \App\UI\Screens\Admin\UsersManager::class, ['reset' => true]);

    $response = $ui->action('permissions_table', 'permissions_table_column_clicked', [
        'sort_by' => 'name',
        'column_text' => 'Name',
    ]);
    $response->assertOk();
    expect($response->json('error'))->toBeNull();
    $ui->component('permissions_table')->expect('sort_column')->toBe('name');

    $ui->assertNoIssues();
});

