<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Spatie\Permission\Models\Role;

it('handles users manager search input event without backend errors', function () {
    Role::findOrCreate('root');

    $root = User::factory()->create();
    $root->assignRole('root');

    $this->actingAs($root);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->input('search_users', 'search_users', [
        'value' => 'elena',
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();

    $ui->assertNoIssues();
});
