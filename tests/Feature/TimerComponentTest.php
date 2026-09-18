<?php

use App\UI\Screens\Device\DevicePairingScreen;
use Idei\Usim\Components\Timer;
use Idei\Usim\UI;

it('creates a timer component with default configuration', function () {
    $timer = UI::timer('test_timer');

    expect($timer)->toBeInstanceOf(Timer::class);

    $json = $timer->toJson()[$timer->getId()];
    expect($json['type'] ?? null)->toBe('timer');
    expect($json['name'] ?? null)->toBe('test_timer');
    expect($json['interval'] ?? null)->toBe(5000);
    expect($json['repeat'] ?? null)->toBeFalse();
    expect($json['enabled'] ?? null)->toBeTrue();
    expect($json['immediate'] ?? null)->toBeFalse();
    expect($json['action'] ?? null)->toBeNull();
    expect($json['parameters'] ?? null)->toBeArray();
});

it('supports fluent builder methods for timer configuration', function () {
    $timer = UI::timer('poller')
        ->action('refresh_data')
        ->every(3500)
        ->immediate()
        ->param('source', 'kiosk');

    $json = $timer->toJson()[$timer->getId()];
    expect($json['action'])->toBe('refresh_data');
    expect($json['interval'])->toBe(3500);
    expect($json['repeat'])->toBeTrue();
    expect($json['immediate'])->toBeTrue();
    expect($json['parameters'])->toBe(['source' => 'kiosk']);

    // Test pause / resume
    $timer->stop();
    expect($timer->toJson()[$timer->getId()]['enabled'])->toBeFalse();

    $timer->start();
    expect($timer->toJson()[$timer->getId()]['enabled'])->toBeTrue();

    // Test one-shot after
    $timer->after(1000);
    $updated = $timer->toJson()[$timer->getId()];
    expect($updated['interval'])->toBe(1000);
    expect($updated['repeat'])->toBeFalse();
});

it('deserializes timer component properly from json array', function () {
    $data = [
        'type' => 'timer',
        'name' => 'sync_timer',
        'action' => 'do_sync',
        'interval' => 2500,
        'repeat' => true,
        'enabled' => true,
        'parent' => 10,
    ];

    $timer = Timer::deserialize(500, $data);
    expect($timer)->toBeInstanceOf(Timer::class);
    expect($timer->getId())->toBe(500);

    $json = $timer->toJson()[500];
    expect($json['type'])->toBe('timer');
    expect($json['name'])->toBe('sync_timer');
    expect($json['action'])->toBe('do_sync');
    expect($json['interval'])->toBe(2500);
    expect($json['repeat'])->toBeTrue();
});

it('exposes timer component in device pairing screen tree', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);

    $timerData = $ui->component('tmr_pairing_poll')->data();
    expect($timerData['type'] ?? null)->toBe('timer');
    expect($timerData['action'] ?? null)->toBe('check_status');
    expect($timerData['interval'] ?? null)->toBe(4000);
    expect($timerData['repeat'] ?? null)->toBeTrue();
    expect($timerData['enabled'] ?? null)->toBeTrue();
});

