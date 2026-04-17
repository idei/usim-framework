<?php

use App\UI\Screens\Demo\FormDemo;

it('loads form demo with expected defaults', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

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

        $result->expect('text')->toBe(t('screen.demo.form_demo.result.initial'));
        $result->expect('style')->toBe('secondary');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('shows required errors when submitting empty form', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, FormDemo::class, ['reset' => true]);

        $response = $ui->click('btn_submit', [
            'input_name' => '',
            'input_email' => '',
        ]);

        $response->assertOk();

        $ui->component('input_name')->expect('error')->toBe(t('screen.demo.form_demo.validation.name_required'));
        $ui->component('input_email')->expect('error')->toBe(t('screen.demo.form_demo.validation.email_required'));

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screen.demo.form_demo.result.errors'));
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
        ]);

        $response->assertOk();

        $ui->component('input_name')->expect('error')->toBe(t('screen.demo.form_demo.validation.name_min'));
        $ui->component('input_email')->expect('error')->toBe(t('screen.demo.form_demo.validation.email_invalid'));

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screen.demo.form_demo.result.errors'));
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

        $response = $ui->click('btn_submit', [
            'input_name' => 'Alice',
            'input_email' => 'alice@example.com',
        ]);

        $response->assertOk();

        $resultText = $ui->component('lbl_result')->data()['text'] ?? '';
        expect($resultText)->toBe(t('screen.demo.form_demo.result.success', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]));

        $ui->component('lbl_result')->expect('style')->toBe('success');
        $ui->component('input_name')->expect('error')->toBeNull();
        $ui->component('input_email')->expect('error')->toBeNull();
        $ui->component('input_name')->expect('value')->toBe('');
        $ui->component('input_email')->expect('value')->toBe('');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
