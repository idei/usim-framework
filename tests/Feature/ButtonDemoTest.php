<?php

use App\UI\Screens\Demo\ButtonDemo;

it('toggles button label on click', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
    $btnToggle = $ui->component('btn_toggle');

    $btnToggle->expect('label')->toBe('Click Me!');
    $btnToggle->click();
    $btnToggle->expect('label')->toBe('Clicked! 🎉');
    $btnToggle->click();
    $btnToggle->expect('label')->toBe('Click Me!');
    $ui->assertNoIssues();
});
