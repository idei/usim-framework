<?php

use App\UI\Screens\Demo\ButtonDemo;

it('loads button demo screen with btn_toggle component', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
    $btnToggle = $ui->component('btn_toggle');

    $btnToggle->expect('label')->toBe('Click Me!');
    $ui->assertNoIssues();
});

it('toggles button label to clicked when toggle_label event is sent', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
    $btnToggle = $ui->component('btn_toggle');

    $btnToggle->expect('label')->toBe('Click Me!');
    $btnToggle->click();
    $btnToggle->expect('label')->toBe('Clicked! 🎉');
    $ui->assertNoIssues();
});

it('replays backend usim as opaque token', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
    $initialUsim = $ui->opaqueUsim();

    expect($initialUsim)->not->toBe('');

    $ui->component('btn_toggle')->click();
    $updatedUsim = $ui->opaqueUsim();

    expect($updatedUsim)->not->toBe('');
    expect($updatedUsim)->not->toBe($initialUsim);
    $ui->assertNoIssues();
});

it('alternates button content on consecutive clicks', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
    $btnToggle = $ui->component('btn_toggle');

    $btnToggle->expect('label')->toBe('Click Me!');
    $btnToggle->expect('style')->toBe('primary');

    $expectedStates = [
        ['label' => 'Clicked! 🎉', 'style' => 'success'],
        ['label' => 'Click Me!', 'style' => 'primary'],
        ['label' => 'Clicked! 🎉', 'style' => 'success'],
        ['label' => 'Click Me!', 'style' => 'primary'],
        ['label' => 'Clicked! 🎉', 'style' => 'success'],
    ];

    foreach ($expectedStates as $expected) {
        $btnToggle->click();
        $btnToggle->expect('label')->toBe($expected['label']);
        $btnToggle->expect('style')->toBe($expected['style']);
    }

    $ui->assertNoIssues();
});
