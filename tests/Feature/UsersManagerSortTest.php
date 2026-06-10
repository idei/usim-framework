<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Spatie\Permission\Models\Role;

it('handles users manager sort action without backend errors', function () {
    Role::findOrCreate('root');

    $root = User::factory()->create();
    $root->assignRole('root');

    $this->actingAs($root);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('users_table', 'users_table_column_clicked', [
        'sort_by' => 'name',
        'column_text' => 'Name',
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();

    $ui->component('users_table')->expect('sort_column')->toBe('name');
    $ui->assertNoIssues();
});
