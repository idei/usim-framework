<?php

use App\Models\Device;
use App\UI\Screens\Device\KioskScreen;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $prev = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId(null);
    }

    $perm = Permission::findOrCreate('device.kiosk_screen.access', 'device');
    $role = UsimRole::firstOrCreate(['name' => 'smart_tv', 'guard_name' => 'device']);
    $role->givePermissionTo($perm);

    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId($prev);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function createAuthenticatedKioskDevice(Tests\TestCase $test): Device
{
    $unit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    setPermissionsTeamId($unit->id);

    $device = Device::create(['name' => 'Kiosk TV Display']);
    $device->usimUnits()->sync([$unit->id]);
    $device->assignRole('smart_tv');

    $test->actingAs($device, 'device');

    return $device;
}

it('redirects unauthenticated client to device pairing screen', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson(screenApiUrl(KioskScreen::class, ['reset' => true]));
    $response->assertOk();
    expect($response->json('redirect'))->toBe(url('/device/device-pairing-screen'));
});

it('loads automated kiosk carousel screen without user interaction controls', function () {
    /** @var Tests\TestCase $this */
    createAuthenticatedKioskDevice($this);

    $ui = uiScenario($this, KioskScreen::class, ['reset' => true]);

    $carousel = $ui->component('device_carousel')->data();
    expect($carousel['type'] ?? null)->toBe('carousel');
    expect($carousel['mode'] ?? null)->toBe('auto');
    expect($carousel['current_index'] ?? null)->toBe(0);
    expect($carousel['loop'] ?? null)->toBe(true);
    expect($carousel['fullscreen'] ?? null)->toBe(false);
    expect($carousel['show_prev'] ?? null)->toBe(false);
    expect($carousel['show_next'] ?? null)->toBe(false);
    expect($carousel['indicator_position'] ?? null)->toBe('none');
    expect($carousel['autoplay']['enabled'] ?? null)->toBe(true);
    expect($carousel['autoplay']['action'] ?? null)->toBe('carousel_tick');

    // Verify there are no manual interaction buttons
    expect(fn () => $ui->component('btn_toggle_fullscreen'))->toThrow(RuntimeException::class);
    expect(fn () => $ui->component('btn_toggle_autoplay'))->toThrow(RuntimeException::class);
    expect(fn () => $ui->component('btn_reset_carousel'))->toThrow(RuntimeException::class);

    $ui->assertNoIssues();
});

it('automatically advances slides on timer tick in continuous loop', function () {
    /** @var Tests\TestCase $this */
    createAuthenticatedKioskDevice($this);

    $ui = uiScenario($this, KioskScreen::class, ['reset' => true]);

    $carousel = $ui->component('device_carousel')->data();
    $carouselId = isset($carousel['_json_key']) ? (int) $carousel['_json_key'] : null;

    expect($carouselId)->toBeInt();
    expect($carousel['current_index'] ?? null)->toBe(0);

    // Slide 1 -> Slide 2
    $ui->timeout($carouselId, 'carousel_tick', [
        'carousel_name' => 'device_carousel',
        'current_index' => 0,
    ])->assertOk();

    $slide2 = $ui->component('device_carousel')->data();
    expect($slide2['current_index'] ?? null)->toBe(1);
    expect($slide2['current_media']['id'] ?? null)->toBe('d2');

    // Slide 2 -> Slide 3
    $ui->timeout($carouselId, 'carousel_tick', [
        'carousel_name' => 'device_carousel',
        'current_index' => 1,
    ])->assertOk();

    $slide3 = $ui->component('device_carousel')->data();
    expect($slide3['current_index'] ?? null)->toBe(2);
    expect($slide3['current_media']['id'] ?? null)->toBe('d3');

    // Slide 3 -> Slide 1 (loop)
    $ui->timeout($carouselId, 'carousel_tick', [
        'carousel_name' => 'device_carousel',
        'current_index' => 2,
    ])->assertOk();

    $loopBack = $ui->component('device_carousel')->data();
    expect($loopBack['current_index'] ?? null)->toBe(0);
    expect($loopBack['current_media']['id'] ?? null)->toBe('d1');

    $ui->assertNoIssues();
});
