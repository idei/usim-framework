<?php

use App\UI\Screens\Admin\UsersManager;

it('handles users search clear after a non-empty term', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

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
