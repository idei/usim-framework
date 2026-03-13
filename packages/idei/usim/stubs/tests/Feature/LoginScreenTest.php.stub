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

it('authenticates user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $password = 'secret123';
    $user = User::factory()->create([
        'email' => 'ui-login@example.com',
        'password' => bcrypt($password),
    ]);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-login@example.com',
            'login_password' => $password,
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($user);
});

it('returns feedback contract for invalid login', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create([
        'email' => 'ui-invalid@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-invalid@example.com',
            'login_password' => 'wrong-password',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
    expect($response->json('toast'))->toBeArray();
});
