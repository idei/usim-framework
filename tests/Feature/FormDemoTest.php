<?php

use App\UI\Screens\Demo\FormDemo;

it('loads form demo with expected defaults', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $name = $ui->component('input_name');
    $email = $ui->component('input_email');
    $submit = $ui->component('btn_submit');
    $result = $ui->component('lbl_result');

    $name->expect('type')->toBe('input');
    $name->expect('required')->toBeTrue();
    $name->expect('value')->toBe('');
    $name->expect('error')->toBeNull();

    $email->expect('type')->toBe('input');
    $email->expect('required')->toBeTrue();
    $email->expect('input_type')->toBe('email');
    $email->expect('value')->toBe('');
    $email->expect('error')->toBeNull();

    $submit->expect('type')->toBe('button');
    $submit->expect('action')->toBe('submit_form');

    $result->expect('text')->toBe('Fill the form to continue');
    $result->expect('style')->toBe('secondary');

    $ui->assertNoIssues();
});

it('shows required errors when submitting empty form', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $response = $ui->click('btn_submit', [
        'input_name' => '',
        'input_email' => '',
    ]);

    $response->assertOk();

    $ui->component('input_name')->expect('error')->toBe('Name is required');
    $ui->component('input_email')->expect('error')->toBe('Email is required');

    $result = $ui->component('lbl_result');
    $result->expect('text')->toBe('❌ Please fix the errors above');
    $result->expect('style')->toBe('danger');

    $ui->assertNoIssues();
});

it('shows format and min-length errors for invalid values', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $response = $ui->click('btn_submit', [
        'input_name' => 'A',
        'input_email' => 'not-an-email',
    ]);

    $response->assertOk();

    $ui->component('input_name')->expect('error')->toBe('Name must be at least 2 characters');
    $ui->component('input_email')->expect('error')->toBe('Email is invalid');

    $result = $ui->component('lbl_result');
    $result->expect('text')->toBe('❌ Please fix the errors above');
    $result->expect('style')->toBe('danger');

    $ui->assertNoIssues();
});

it('submits successfully and clears inputs for valid values', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $response = $ui->click('btn_submit', [
        'input_name' => 'Alice',
        'input_email' => 'alice@example.com',
    ]);

    $response->assertOk();

    $resultText = $ui->component('lbl_result')->data()['text'] ?? '';
    expect($resultText)->toContain('Form submitted successfully!');
    expect($resultText)->toContain('Name: Alice');
    expect($resultText)->toContain('Email: alice@example.com');

    $ui->component('lbl_result')->expect('style')->toBe('success');
    $ui->component('input_name')->expect('error')->toBeNull();
    $ui->component('input_email')->expect('error')->toBeNull();
    $ui->component('input_name')->expect('value')->toBe('');
    $ui->component('input_email')->expect('value')->toBe('');

    $ui->assertNoIssues();
});
