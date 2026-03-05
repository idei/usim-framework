<?php

use App\UI\Screens\Demo\ButtonDemo;
use Idei\Usim\Services\Support\UIIdGenerator;
use Idei\Usim\Services\UIChangesCollector;

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

it('supports overriding store variables in scenario', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true])
        ->setStore('store_state', true);

    expect($ui->store('store_state'))->toBeTrue();

    $ui->component('btn_toggle')->click();

    expect($ui->store('store_state'))->toBeFalse();
    $ui->assertNoIssues();
});

it('alternates button content on consecutive clicks', function () {
    $componentId = UIIdGenerator::generateFromName(ButtonDemo::class, 'btn_toggle');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => false]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result1 = app(UIChangesCollector::class)->all();
    expect($result1[(string) $componentId]['label'] ?? null)->toBe('Clicked! 🎉');
    expect($result1[(string) $componentId]['style'] ?? null)->toBe('success');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => true]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result2 = app(UIChangesCollector::class)->all();
    expect($result2[(string) $componentId]['label'] ?? null)->toBe('Click Me!');
    expect($result2[(string) $componentId]['style'] ?? null)->toBe('primary');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => false]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result3 = app(UIChangesCollector::class)->all();
    expect($result3[(string) $componentId]['label'] ?? null)->toBe('Clicked! 🎉');
    expect($result3[(string) $componentId]['style'] ?? null)->toBe('success');
});
