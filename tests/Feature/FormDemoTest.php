<?php

use App\UI\Screens\Demo\FormDemo;

it('loads form demo with expected defaults', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

        $name = $ui->component('input_name');
        $email = $ui->component('input_email');
        $message = $ui->component('input_message');
        $submit = $ui->component('btn_submit');
        $reset = $ui->component('btn_reset');
        $result = $ui->component('lbl_result');
        $instruction = $ui->component('lbl_instruction');

        $instruction->expect('type')->toBe('newlabel');
        $instruction->expect('text')->toBe(t('screens.demo.form_demo.instruction'));
        $instruction->expect('grid_column')->toBe('span 2');
        $instruction->expect('justify_self')->toBe('center');

        $name->expect('type')->toBe('input');
        $name->expect('required')->toBeTrue();
        $name->expect('value')->toBe('');
        $name->expect('error')->toBeNull();
        $name->expect('grid_column')->toBe('span 2');

        $email->expect('type')->toBe('input');
        $email->expect('required')->toBeTrue();
        $email->expect('input_type')->toBe('email');
        $email->expect('value')->toBe('');
        $email->expect('error')->toBeNull();
        $email->expect('grid_column')->toBe('span 2');

        $message->expect('type')->toBe('textarea');
        $message->expect('required')->toBeTrue();
        $message->expect('value')->toBeNull();
        $message->expect('error')->toBeNull();
        $message->expect('min_length')->toBe(10);
        $message->expect('max_length')->toBe(50);
        $message->expect('grid_column')->toBe('span 2');

        $submit->expect('type')->toBe('button');
        $submit->expect('action')->toBe('submit_form');
        $submit->expect('grid_column')->toBe('span 1');

        $reset->expect('type')->toBe('button');
        $reset->expect('action')->toBe('reset_form');
        $reset->expect('label')->toBe(t('screens.demo.form_demo.actions.reset'));
        $reset->expect('grid_column')->toBe('span 1');

        $result->expect('type')->toBe('newlabel');
        $result->expect('text')->toBe(t('screens.demo.form_demo.result.initial'));
        $result->expect('style')->toBe('secondary');
        $result->expect('grid_column')->toBe('span 2');
        $result->expect('justify_self')->toBe('center');

        $wrapper = $ui->component('form_wrapper');
        $wrapper->expect('layout')->toBe('grid');
        $wrapper->expect('grid_template_columns')->toBe('1fr 1fr');
        $wrapper->expect('grid_template_rows')->toBe('auto auto auto 1fr auto auto');
        $wrapper->expect('gap')->toBe('16px');
        $wrapper->expect('padding')->toBe('24px');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('clears error when field becomes valid on subsequent submit', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $ui->click('btn_submit', [
        'input_name' => '',
        'input_email' => '',
    ]);

    $ui->component('input_name')->expect('error')->not->toBeNull();

    $ui->click('btn_submit', [
        'input_name' => 'Popitossssss',
        'input_email' => '',
    ]);

    $ui->component('input_name')->expect('error')->toBeNull();
});

it('shows required errors when submitting empty form', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

        $response = $ui->click('btn_submit', [
            'input_name' => '',
            'input_email' => '',
            'input_message' => '',
        ]);

        $response->assertOk();

        $ui->component('input_name')->expect('error')->toBe(t('screens.demo.form_demo.validation.name_required'));
        $ui->component('input_email')->expect('error')->toBe(t('screens.demo.form_demo.validation.email_required'));

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screens.demo.form_demo.result.errors'));
        $result->expect('style')->toBe('danger');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('shows format and min-length errors for invalid values', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

        $response = $ui->click('btn_submit', [
            'input_name' => 'A',
            'input_email' => 'not-an-email',
            'input_message' => 'Short',
        ]);

        $response->assertOk();

        $ui->component('input_name')->expect('error')->toBe(t('screens.demo.form_demo.validation.name_min'));
        $ui->component('input_email')->expect('error')->toBe(t('screens.demo.form_demo.validation.email_invalid'));
        $ui->component('input_message')->expect('error')->toBe(t('screens.demo.form_demo.validation.message_min'));

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screens.demo.form_demo.result.errors'));
        $result->expect('style')->toBe('danger');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('submits successfully and clears inputs for valid values', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

        $name = 'Alice Wonderland';
        $email = 'alice@example.com';
        $message = 'This is a valid test message.';

        $response = $ui->click('btn_submit', [
            'input_name' => $name,
            'input_email' => $email,
            'input_message' => $message,
        ]);

        $response->assertOk();

        $resultText = $ui->component('lbl_result')->data()['text'] ?? '';
        expect($resultText)->toBe(t('screens.demo.form_demo.result.success', [
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]));

        $ui->component('lbl_result')->expect('style')->toBe('success');
        $ui->component('input_name')->expect('error')->toBeNull();
        $ui->component('input_email')->expect('error')->toBeNull();
        $ui->component('input_message')->expect('error')->toBeNull();
        $ui->component('input_name')->expect('value')->toBe('');
        $ui->component('input_email')->expect('value')->toBe('');
        $ui->component('input_message')->expect('value')->toBe('');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('resets form values and clears validation errors when clicking reset button', function () {
    $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

    $ui->click('btn_submit', [
        'input_name' => 'A',
        'input_email' => 'invalid-email',
        'input_message' => 'Short',
    ]);

    $ui->component('input_name')->expect('error')->not->toBeNull();
    $ui->component('input_email')->expect('error')->not->toBeNull();
    $ui->component('input_message')->expect('error')->not->toBeNull();
    $ui->component('lbl_result')->expect('style')->toBe('danger');

    $response = $ui->click('btn_reset');
    $response->assertOk();

    $ui->component('input_name')->expect('value')->toBe('');
    $ui->component('input_name')->expect('error')->toBeNull();
    $ui->component('input_email')->expect('value')->toBe('');
    $ui->component('input_email')->expect('error')->toBeNull();
    $ui->component('input_message')->expect('value')->toBe('');
    $ui->component('input_message')->expect('error')->toBeNull();
    $ui->component('lbl_result')->expect('text')->toBe(t('screens.demo.form_demo.result.initial'));
    $ui->component('lbl_result')->expect('style')->toBe('secondary');

    $ui->assertNoIssues();
});
