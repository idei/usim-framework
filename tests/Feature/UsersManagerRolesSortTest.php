<?php

use App\Models\User;
use App\Services\Role\RoleListingService;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Models\UsimRole;

it('correctly sorts roles by name, home_screen and priority in RoleListingService', function () {
    $adminRole = UsimRole::createWithHome('admin_test', 'admin_home', 10);
    $editorRole = UsimRole::createWithHome('editor_test', 'editor_home', 50);
    $guestRole = UsimRole::createWithHome('guest_test', 'guest_home', 5);

    $service = app(RoleListingService::class);

    // Sort by name ASC
    $sortedByNameAsc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'name', sortDirection: 'asc');
    $namesAsc = array_map(fn (UsimRole $r) => $r->name, $sortedByNameAsc['items']);
    expect($namesAsc)->toBe(['admin_test', 'editor_test', 'guest_test']);

    // Sort by name DESC
    $sortedByNameDesc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'name', sortDirection: 'desc');
    $namesDesc = array_map(fn (UsimRole $r) => $r->name, $sortedByNameDesc['items']);
    expect($namesDesc)->toBe(['guest_test', 'editor_test', 'admin_test']);

    // Sort by home_screen ASC
    $sortedByHomeAsc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'home_screen', sortDirection: 'asc');
    $homesAsc = array_map(fn (UsimRole $r) => $r->home_screen, $sortedByHomeAsc['items']);
    expect($homesAsc)->toBe(['admin_home', 'editor_home', 'guest_home']);

    // Sort by home_screen DESC
    $sortedByHomeDesc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'home_screen', sortDirection: 'desc');
    $homesDesc = array_map(fn (UsimRole $r) => $r->home_screen, $sortedByHomeDesc['items']);
    expect($homesDesc)->toBe(['guest_home', 'editor_home', 'admin_home']);


    // Sort by priority ASC
    $sortedByPriorityAsc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'priority', sortDirection: 'asc');
    $priorityAsc = array_map(fn (UsimRole $r) => $r->priority, $sortedByPriorityAsc['items']);
    expect($priorityAsc)->toBe([5, 10, 50]);

    // Sort by priority DESC
    $sortedByPriorityDesc = $service->paginate(page: 1, perPage: 10, search: '_test', sortField: 'priority', sortDirection: 'desc');
    $priorityDesc = array_map(fn (UsimRole $r) => $r->priority, $sortedByPriorityDesc['items']);
    expect($priorityDesc)->toBe([50, 10, 5]);
});

it('handles roles table column clicked sorting in UsersManager screen', function () {
    UsimRole::findOrCreate('root');

    $root = User::factory()->create();
    $root->assignRole('root');

    $this->actingAs($root);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Click sort by name
    $response = $ui->action('roles_table', 'roles_table_column_clicked', [
        'sort_by' => 'name',
        'column_text' => 'Name',
    ]);
    $response->assertOk();
    expect($response->json('error'))->toBeNull();
    $ui->component('roles_table')->expect('sort_column')->toBe('name');

    // Click sort by home_screen
    $response = $ui->action('roles_table', 'roles_table_column_clicked', [
        'sort_by' => 'home_screen',
        'column_text' => 'Home Screen',
    ]);
    $response->assertOk();
    expect($response->json('error'))->toBeNull();
    $ui->component('roles_table')->expect('sort_column')->toBe('home_screen');

    // Click sort by priority
    $response = $ui->action('roles_table', 'roles_table_column_clicked', [
        'sort_by' => 'priority',
        'column_text' => 'Priority',
    ]);
    $response->assertOk();
    expect($response->json('error'))->toBeNull();
    $ui->component('roles_table')->expect('sort_column')->toBe('priority');

    $ui->assertNoIssues();
});
