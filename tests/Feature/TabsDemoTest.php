<?php

use App\UI\Screens\Demo\TabsDemo;

it('loads tabs demo with container tabs and simplified configuration', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $tabs = $ui->component('tabs_container')->data();

    expect($tabs['type'] ?? null)->toBe('container');
    expect($tabs['tabs_active'] ?? null)->toBe('overview');
    expect($tabs['tabs_on_change'] ?? null)->toBe('tabs_switch');
    expect($tabs['tabs_on_close'] ?? null)->toBe('tabs_close');

    $tabIds = array_map(static fn (array $tab): string => (string) ($tab['id'] ?? ''), $tabs['tabs'] ?? []);
    expect($tabIds)->toBe(['overview', 'activity', 'settings', 'advanced']);

    $activityTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'activity');
    expect($activityTab['closable'] ?? null)->toBeTrue();

    $ui->component('tabs_overview_body')->expect('tab')->toBe('overview');
    $ui->component('tabs_activity_log')->expect('tab')->toBe('activity');

    $ui->assertNoIssues();
});

it('updates the active tab from the backend change handler', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $ui->change('tabs_container', 'tabs_switch', [
        'tab_id' => 'activity',
    ])->assertOk();

    $tabs = $ui->component('tabs_container')->data();
    expect($tabs['tabs_active'] ?? null)->toBe('activity');

    $ui->assertNoIssues();
});

it('handles tab close action from the backend', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $ui->action('tabs_container', 'tabs_close', [
        'tab_id' => 'activity',
    ])->assertOk();

    // El handler solo envía un toast, la tab persiste
    $tabs = $ui->component('tabs_container')->data();
    $tabIds = array_map(static fn (array $tab): string => (string) ($tab['id'] ?? ''), $tabs['tabs'] ?? []);

    expect($tabIds)->toBe(['overview', 'activity', 'settings', 'advanced']);

    $ui->assertNoIssues();
});

it('renders advanced tab without explicit colors using theme token defaults', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $tabs = $ui->component('tabs_container')->data();

    $advancedTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'advanced');
    expect($advancedTab)->not->toBeNull('Advanced tab debe existir');
    expect($advancedTab['label'] ?? null)->toBe(t('screen.demo.tabs_demo.tabs.advanced.label'));

    // Verificar que el tab no tiene colores definidos (fallback a tema)
    expect($advancedTab['color'] ?? null)->toBeNull('No debe tener color definido');

    $ui->component('tabs_advanced_info')->expect('tab')->toBe('advanced');

    $ui->assertNoIssues();
});
