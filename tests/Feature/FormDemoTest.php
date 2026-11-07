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
        $result = $ui->component('lbl_result');
        $instruction = $ui->component('lbl_instruction');

        $instruction->expect('type')->toBe('newlabel');
        $instruction->expect('text')->toBe(t('screens.demo.form_demo.instruction'));

        $name->expect('type')->toBe('input');
        $name->expect('required')->toBeTrue();
        $name->expect('value')->toBe('');
        $name->expect('error')->toBeNull();

        $email->expect('type')->toBe('input');
        $email->expect('required')->toBeTrue();
        $email->expect('input_type')->toBe('email');
        $email->expect('value')->toBe('');
        $email->expect('error')->toBeNull();

        $message->expect('type')->toBe('textarea');
        $message->expect('required')->toBeTrue();
        $message->expect('value')->toBe('');
        $message->expect('error')->toBeNull();

        $submit->expect('type')->toBe('button');
        $submit->expect('action')->toBe('submit_form');

        $result->expect('type')->toBe('newlabel');
        $result->expect('text')->toBe(t('screens.demo.form_demo.result.initial'));
        $result->expect('style')->toBe('secondary');

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
            'input_message' => 'Short message',
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
        $message = 'This is a valid test message that exceeds fifty characters in total length.';

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
