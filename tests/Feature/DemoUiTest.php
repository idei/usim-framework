<?php

use App\UI\Screens\Demo\DemoUi;

it('loads demo ui with expected initial state', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

        $ui->component('lbl_welcome')->expect('type')->toBe('label');
        $ui->component('lbl_welcome')->expect('text')->toBe(t('screen.demo.demo_ui.welcome.initial'));

        $ui->component('btn_test_update')->expect('action')->toBe('test_action');
        $ui->component('btn_test_add')->expect('action')->toBe('add_new_component');

        $counter = $ui->component('lbl_counter');
        $counter->expect('text')->toBe('1000');
        $counter->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('updates welcome label when test update button is clicked', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

        $response = $ui->click('btn_test_update');
        $response->assertOk();

        $welcomeText = $ui->component('lbl_welcome')->data()['text'] ?? '';
        expect($welcomeText)->not->toBe(t('screen.demo.demo_ui.welcome.initial'));
        expect($welcomeText)->not->toBe('');
        $ui->component('lbl_welcome')->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('increments and decrements interactive counter', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

        $ui->component('btn_increment')->click();
        $ui->component('lbl_counter')->expect('text')->toBe('1001');

        $ui->component('btn_decrement')->click();
        $ui->component('lbl_counter')->expect('text')->toBe('1000');

        $ui->component('lbl_counter')->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('adds a new dynamic button when add action is triggered', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

        $response = $ui->click('btn_test_add');
        $response->assertOk();

        $newButton = $ui->component('btn_new_button_1');
        $newButton->expect('type')->toBe('button');
        $newButton->expect('action')->toBe('new_button_action');
        $newButton->expect('label')->toBe(
            t('screen.demo.demo_ui.dynamic.button_label', ['number' => 1])
        );

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
