<?php

use App\Models\Device;
use App\UI\Screens\Device\DevicePairingScreen;
use App\UI\Screens\Device\KioskScreen;
use Idei\Usim\Support\DevicePairingManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

it('loads device pairing screen and exposes pin with storage variables in initial payload', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);

    $pinLabel = $ui->component('lbl_pin_display')->data();
    expect($pinLabel['type'] ?? null)->toBe('label');
    $pin = $pinLabel['text'] ?? '';
    expect(strlen($pin))->toBe(4);
    expect(ctype_digit($pin))->toBeTrue();

    // Verify storage was captured by memory renderer on initial load
    $opaque = $ui->opaqueUsim();
    expect($opaque)->not->toBeEmpty();
    $decodedStorage = json_decode($opaque, true);
    expect($decodedStorage)->toBeArray();
    expect($decodedStorage)->toHaveKey('store_session_token');
    expect($decodedStorage)->toHaveKey('store_pin');
    expect($decodedStorage['store_pin'])->toBe($pin);
    expect($decodedStorage['store_session_token'])->not->toBeEmpty();
});

it('preserves existing active pairing session across reloads when storage is replayed', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);
    $initialPin = $ui->component('lbl_pin_display')->data()['text'] ?? '';
    expect($initialPin)->not->toBeEmpty();

    $opaque = $ui->opaqueUsim();

    // Second request with same storage
    $response = $this->withHeaders([
        'X-USIM-Storage' => $opaque,
    ])->getJson(screenApiUrl(DevicePairingScreen::class));

    $response->assertOk();
    $component = findComponentByName($response->json(), 'lbl_pin_display');
    expect($component)->not->toBeNull();
    expect($component['text'] ?? null)->toBe($initialPin);
});

it('handles onCheckStatus when authorization is still pending', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);

    $response = $ui->click('btn_check_status');
    $response->assertOk();

    $statusLabel = $ui->component('lbl_status')->data();
    expect($statusLabel['text'] ?? '')->toContain('Aún esperando autorización...');
    expect($statusLabel['style'] ?? '')->toBe('info');

    // session token and pin must remain intact in storage
    $decodedStorage = json_decode($ui->opaqueUsim(), true);
    expect($decodedStorage['store_session_token'])->not->toBeEmpty();
    expect($decodedStorage['store_pin'])->not->toBeEmpty();
});

it('completes pairing on check status when approved by administrator and redirects to kiosk', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);
    $pin = $ui->component('lbl_pin_display')->data()['text'] ?? '';
    expect($pin)->not->toBeEmpty();

    $device = Device::create(['name' => 'Living Room TV']);

    // Admin approves the device pairing in backend
    $manager = app(DevicePairingManager::class);
    $approved = $manager->approve($pin, $device);
    expect($approved)->toBeTrue();

    $response = $ui->click('btn_check_status');
    $response->assertOk();

    // Verify redirect to KioskScreen
    expect($response->json('redirect'))->toBe(KioskScreen::getRoutePath());

    // Verify device guard login
    expect(Auth::guard('device')->check())->toBeTrue();
    expect(Auth::guard('device')->id())->toBe($device->id);

    // Verify storage updated: store_token is set, session/pin cleared
    $decodedStorage = json_decode($ui->opaqueUsim(), true);
    expect($decodedStorage['store_token'])->not->toBeEmpty();
    expect($decodedStorage['store_session_token'] ?? '')->toBe('');
    expect($decodedStorage['store_pin'] ?? '')->toBe('');
});

it('handles expired session during check status by redirecting to refresh', function () {
    /** @var Tests\TestCase $this */
    $ui = uiScenario($this, DevicePairingScreen::class, ['reset' => true]);
    $decoded = json_decode($ui->opaqueUsim(), true);
    $sessionToken = $decoded['store_session_token'];

    // Invalidate session cache
    Cache::forget("usim_pairing_session:{$sessionToken}");

    $response = $ui->click('btn_check_status');
    $response->assertOk();

    expect($response->json('redirect'))->toBe(DevicePairingScreen::getRoutePath());
    expect($response->json('toast.type'))->toBe('warning');
});

