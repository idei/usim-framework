<?php

use App\UI\Screens\Demo\SplitDemo;

it('loads split demo with split component defaults', function () {
    $ui = uiScenario($this, SplitDemo::class, ['reset' => true]);

    $split = $ui->component('split_workspace')->data();

    expect($split['type'] ?? null)->toBe('split');
    expect($split['split_orientation'] ?? null)->toBe('horizontal');
    expect($split['split_size'] ?? null)->toBe('45%');
    expect($split['splitter_size'] ?? null)->toBe('8px');
    expect($split['draggable'] ?? null)->toBeTrue();
    expect($split['collapsible'] ?? null)->toBeTrue();

    $ui->assertNoIssues();
});

it('updates orientation and split size from controls', function () {
    $ui = uiScenario($this, SplitDemo::class, ['reset' => true]);

    $ui->change('sel_split_orientation', 'split_orientation_change', [
        'value' => 'vertical',
    ])->assertOk();

    $ui->action('btn_apply_split_size', 'apply_split_size', [
        'inp_split_size' => '35%',
        'inp_splitter_size' => '12px',
    ])->assertOk();

    $split = $ui->component('split_workspace')->data();
    expect($split['split_orientation'] ?? null)->toBe('vertical');
    expect($split['split_size'] ?? null)->toBe('35%');
    expect($split['splitter_size'] ?? null)->toBe('12px');

    $ui->assertNoIssues();
});

it('collapses and expands panels through actions', function () {
    $ui = uiScenario($this, SplitDemo::class, ['reset' => true]);

    $ui->action('btn_collapse_first', 'collapse_first_panel')->assertOk();
    $splitAfterFirst = $ui->component('split_workspace')->data();
    expect($splitAfterFirst['collapsed_panel'] ?? null)->toBe('first');

    $ui->action('btn_collapse_second', 'collapse_second_panel')->assertOk();
    $splitAfterSecond = $ui->component('split_workspace')->data();
    expect($splitAfterSecond['collapsed_panel'] ?? null)->toBe('second');

    $ui->action('btn_toggle_split_collapse', 'toggle_split_collapse')->assertOk();
    $splitExpanded = $ui->component('split_workspace')->data();
    expect($splitExpanded['collapsed_panel'] ?? null)->toBeNull();

    $ui->assertNoIssues();
});
