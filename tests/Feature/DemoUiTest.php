<?php

use App\UI\Screens\Demo\DemoUi;

it('loads demo ui with expected initial state', function () {
    $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

    $ui->component('lbl_welcome')->expect('type')->toBe('label');
    expect($ui->component('lbl_welcome')->data()['text'] ?? '')->toContain('Estado inicial');

    $ui->component('btn_test_update')->expect('action')->toBe('test_action');
    $ui->component('btn_test_add')->expect('action')->toBe('open_settings');

    $counter = $ui->component('lbl_counter');
    $counter->expect('text')->toBe('1000');
    $counter->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('updates welcome label when test update button is clicked', function () {
    $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

    $response = $ui->click('btn_test_update');
    $response->assertOk();

    $welcomeText = $ui->component('lbl_welcome')->data()['text'] ?? '';
    expect($welcomeText)->toContain('Botón presionado');
    expect($welcomeText)->toContain('Hora actual');
    $ui->component('lbl_welcome')->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('increments and decrements interactive counter', function () {
    $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

    $ui->component('btn_increment')->click();
    $ui->component('lbl_counter')->expect('text')->toBe('1001');

    $ui->component('btn_decrement')->click();
    $ui->component('lbl_counter')->expect('text')->toBe('1000');

    $ui->component('lbl_counter')->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('adds settings label dynamically when open settings action is triggered', function () {
    $ui = uiScenario($this, DemoUi::class, ['reset' => true]);

    $response = $ui->click('btn_test_add');
    $response->assertOk();

    $payload = $response->json();
    expect(demoUiPayloadContainsText($payload, 'Settings panel opened!'))->toBeTrue();
    expect(demoUiPayloadContainsStyle($payload, 'warning'))->toBeTrue();

    $ui->assertNoIssues();
});
