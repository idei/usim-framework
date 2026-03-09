<?php

use App\UI\Screens\Demo\CarouselDemo;

it('loads carousel demo with manual and auto carousels', function () {
    $ui = uiScenario($this, CarouselDemo::class, ['reset' => true]);

    $manual = $ui->component('manual_carousel')->data();
    expect($manual['type'] ?? null)->toBe('carousel');
    expect($manual['mode'] ?? null)->toBe('manual');

    $auto = $ui->component('auto_carousel')->data();
    expect($auto['type'] ?? null)->toBe('carousel');
    expect($auto['mode'] ?? null)->toBe('auto');

    $ui->assertNoIssues();
});

it('moves manual carousel with next and prev actions', function () {
    $ui = uiScenario($this, CarouselDemo::class, ['reset' => true]);

    $before = $ui->component('manual_carousel')->data();
    expect($before['current_index'] ?? null)->toBe(0);

    $ui->action('manual_carousel', 'carousel_next', [
        'carousel_name' => 'manual_carousel',
    ])->assertOk();

    $afterNext = $ui->component('manual_carousel')->data();
    expect($afterNext['current_index'] ?? null)->toBe(1);

    $ui->action('manual_carousel', 'carousel_prev', [
        'carousel_name' => 'manual_carousel',
    ])->assertOk();

    $afterPrev = $ui->component('manual_carousel')->data();
    expect($afterPrev['current_index'] ?? null)->toBe(0);

    $ui->assertNoIssues();
});

it('updates auto carousel timeout dynamically on timeout tick', function () {
    $ui = uiScenario($this, CarouselDemo::class, ['reset' => true]);

    $auto = $ui->component('auto_carousel')->data();
    $autoId = $auto['_id'] ?? null;

    expect($autoId)->toBeInt();
    expect($auto['current_index'] ?? null)->toBe(0);

    $ui->timeout($autoId, 'carousel_tick', [
        'carousel_name' => 'auto_carousel',
        'current_index' => 0,
    ])->assertOk();

    $updated = $ui->component('auto_carousel')->data();

    expect($updated['current_index'] ?? null)->toBe(1);

    $ui->assertNoIssues();
});
