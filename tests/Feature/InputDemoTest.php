<?php

use App\UI\Screens\Demo\InputDemo;

it('loads input demo screen with expected components', function () {
    $ui = uiScenario($this, InputDemo::class, ['reset' => true]);

    $input = $ui->component('input_text');
    $button = $ui->component('btn_get_value');
    $result = $ui->component('lbl_result');

    $input->expect('type')->toBe('input');
    $button->expect('type')->toBe('button');
    $button->expect('action')->toBe('get_value');
    $result->expect('text')->toBe('Result will appear here');
    $result->expect('style')->toBe('default');

    $ui->assertNoIssues();
});

it('shows required validation error when input is empty', function () {
    $ui = uiScenario($this, InputDemo::class, ['reset' => true]);

    $response = $ui->click('btn_get_value', ['input_text' => '']);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('error');
    expect($response->json('toast.message'))->toBe('Name is required');

    $input = $ui->component('input_text');
    $result = $ui->component('lbl_result');

    $input->expect('error')->toBe('Name is required');
    $result->expect('text')->toBe('❌ Please fix the error above');
    $result->expect('style')->toBe('danger');

    $ui->assertNoIssues();
});

it('shows min-length validation error when input is too short', function () {
    $ui = uiScenario($this, InputDemo::class, ['reset' => true]);

    $response = $ui->click('btn_get_value', ['input_text' => 'ab']);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('error');
    expect($response->json('toast.message'))->toBe('Name must be at least 3 characters');

    $input = $ui->component('input_text');
    $result = $ui->component('lbl_result');

    $input->expect('error')->toBe('Name must be at least 3 characters');
    $result->expect('text')->toBe('❌ Please fix the error above');
    $result->expect('style')->toBe('danger');

    $ui->assertNoIssues();
});

it('shows success result for valid input', function () {
    $ui = uiScenario($this, InputDemo::class, ['reset' => true]);

    $response = $ui->click('btn_get_value', ['input_text' => 'Alice']);

    $response->assertOk();

    $input = $ui->component('input_text');
    $result = $ui->component('lbl_result');

    $input->expect('error')->toBeNull();
    $result->expect('text')->toBe('✅ Valid name: "Alice"');
    $result->expect('style')->toBe('success');

    $ui->assertNoIssues();
});
