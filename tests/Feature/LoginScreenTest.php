<?php

use App\Models\User;
use App\UI\Screens\Auth\Login;

it('loads login screen with expected components and actions', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, Login::class, ['reset' => true]);

    $emailInput = $ui->component('login_email');
    $passwordInput = $ui->component('login_password');
    $submitButton = $ui->component('btn_submit_login');
    $forgotButton = $ui->component('btn_forgot_password');

    $emailInput->expect('type')->toBe('input');
    $emailInput->expect('input_type')->toBe('email');

    $passwordInput->expect('type')->toBe('input');
    $passwordInput->expect('input_type')->toBe('password');

    $submitButton->expect('action')->toBe('submit_login');
    $forgotButton->expect('action')->toBe('navigate_forgot_password');

    $ui->assertNoIssues();
});

it('authenticates configured admin user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $result = $this->loginAs('admin');
    /** @var User $adminUser */
    $adminUser = $result['user'];
    $response = $result['response'];

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($adminUser);
});

it('authenticates configured regular user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $result = $this->loginAs('user');
    /** @var User $regularUser */
    $regularUser = $result['user'];
    $response = $result['response'];

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($regularUser);
});
