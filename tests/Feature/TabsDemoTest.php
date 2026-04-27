<?php

use App\UI\Screens\Demo\TabsDemo;

it('loads tabs demo with themed tabs contract and child tab assignment', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $tabs = $ui->component('tabs_workspace')->data();

    expect($tabs['type'] ?? null)->toBe('container');
    expect($tabs['tabs_active'] ?? null)->toBe('overview');
    expect($tabs['tabs_on_change'] ?? null)->toBe('tabs_switch');
    expect($tabs['tabs_on_close'] ?? null)->toBe('tabs_close');
    expect($tabs['tabs_colors']['disabled_tab_color'] ?? null)->toBe('var(--ui-surface-muted)');

    $tabIds = array_map(static fn (array $tab): string => (string) ($tab['id'] ?? ''), $tabs['tabs'] ?? []);
    expect($tabIds)->toBe(['overview', 'activity', 'reports', 'settings', 'advanced']);

    $reportsTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'reports');
    expect($reportsTab['disabled'] ?? null)->toBeTrue();
    expect($reportsTab['disabled_color'] ?? null)->toBe('#e2e8f0');

    $activityTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'activity');
    expect($activityTab['closable'] ?? null)->toBeTrue();

    $ui->component('tabs_overview_body')->expect('tab')->toBe('overview');
    $ui->component('tabs_activity_log')->expect('tab')->toBe('activity');
    $ui->component('tabs_reports_state')->expect('tab')->toBe('reports');

    $ui->assertNoIssues();
});

it('updates the active tab from the backend change handler', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $ui->change('tabs_workspace', 'tabs_switch', [
        'tab_id' => 'activity',
        'tab_name' => 'Actividad',
    ])->assertOk();

    $tabs = $ui->component('tabs_workspace')->data();
    expect($tabs['tabs_active'] ?? null)->toBe('activity');

    $ui->assertNoIssues();
});

it('closes a closable tab and removes it from the contract', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $ui->change('tabs_workspace', 'tabs_switch', [
        'tab_id' => 'settings',
        'tab_name' => 'Configuracion',
    ])->assertOk();

    $ui->action('tabs_workspace', 'tabs_close', [
        'tab_id' => 'settings',
        'tab_name' => 'Configuracion',
    ])->assertOk();

    $tabs = $ui->component('tabs_workspace')->data();
    $tabIds = array_map(static fn (array $tab): string => (string) ($tab['id'] ?? ''), $tabs['tabs'] ?? []);

    expect($tabIds)->toBe(['overview', 'activity', 'reports', 'advanced']);
    expect($tabs['tabs_active'] ?? null)->toBe('overview');

    $ui->assertNoIssues();
});

it('enables the disabled reports tab from backend and activates it', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $ui->action('btn_enable_reports', 'enable_reports_tab')->assertOk();

    $tabs = $ui->component('tabs_workspace')->data();
    $reportsTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'reports');

    expect($reportsTab['disabled'] ?? null)->toBeFalse();
    expect($tabs['tabs_active'] ?? null)->toBe('reports');
    $ui->component('tabs_reports_state')->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('renders advanced tab without explicit colors using theme token defaults', function () {
    $ui = uiScenario($this, TabsDemo::class, ['reset' => true]);

    $tabs = $ui->component('tabs_workspace')->data();

    $advancedTab = collect($tabs['tabs'] ?? [])->firstWhere('id', 'advanced');
    expect($advancedTab)->not->toBeNull('Advanced tab debe existir');
    expect($advancedTab['label'] ?? null)->toBe('Avanzado');

    // Verificar que el tab no tiene colores definidos (fallback a tema)
    expect($advancedTab['color'] ?? null)->toBeNull('No debe tener color definido');
    expect($advancedTab['text_color'] ?? null)->toBeNull('No debe tener text_color definido');
    expect($advancedTab['active_color'] ?? null)->toBeNull('No debe tener active_color definido');
    expect($advancedTab['active_text_color'] ?? null)->toBeNull('No debe tener active_text_color definido');

    // El componente dentro debe estar asociado al tab
    $ui->component('tabs_advanced_info')->expect('tab')->toBe('advanced');

    $ui->assertNoIssues();
});
