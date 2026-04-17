<?php

use App\UI\Screens\Demo\ButtonDemo;

it('toggles button label on click', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);
        $btnToggle = $ui->component('btn_toggle');

        $btnToggle->expect('label')->toBe(t('screen.demo.button_demo.default'));
        $btnToggle->click();
        $btnToggle->expect('label')->toBe(t('screen.demo.button_demo.clicked'));
        $btnToggle->click();
        $btnToggle->expect('label')->toBe(t('screen.demo.button_demo.default'));
        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
