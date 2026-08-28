<?php

use App\UI\Screens\Admin\UsersManager;

it('handles users manager search input event without backend errors', function () {

    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->input('search_users', 'search_users', [
        'value' => 'elena',
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();

    $ui->assertNoIssues();
});
