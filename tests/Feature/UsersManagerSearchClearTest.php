<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Spatie\Permission\Models\Role;

it('handles users search clear after a non-empty term', function () {
    Role::findOrCreate('root');

    $root = User::factory()->create();
    $root->assignRole('root');

    $this->actingAs($root);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $first = $ui->input('search_users', 'search_users', [
        'value' => 'elena',
    ]);
    $first->assertOk();

    $second = $ui->input('search_users', 'search_users', [
        'value' => '',
    ]);
    $second->assertOk();

    expect($second->json('error'))->toBeNull();

    $ui->component('search_users')->expect('value')->toBe('');
    $ui->assertNoIssues();
});
