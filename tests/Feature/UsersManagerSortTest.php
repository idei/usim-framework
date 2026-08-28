<?php

use App\UI\Screens\Admin\UsersManager;

it('handles users manager sort action without backend errors', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

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
