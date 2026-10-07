<?php

use App\Models\Device;
use App\UI\Screens\Device\Link;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\DevicePairingManager;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    UsimRole::firstOrCreate(['name' => 'smart_tv', 'guard_name' => 'device']);
});

it('loads link device screen with pre-selected device', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Auditorio']);

    $uiResponse = getScreenJson($this, Link::class, ['device_id' => $device->id]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $dialog = findComponentByName($payload, 'device_pairing_dialog');
    $title = findComponentByName($payload, 'dialog_title');
    $instruction = findComponentByName($payload, 'dialog_instruction');
    $selectedLabel = findComponentByName($payload, 'lbl_selected_device');
    $pairingDeviceId = findComponentByName($payload, 'pairing_device_id');
    $pinInput = findComponentByName($payload, 'input_pin');
    $cancelBtn = findComponentByName($payload, 'btn_cancel_pairing');
    $submitBtn = findComponentByName($payload, 'btn_submit_pairing');

    expect($dialog)->not->toBeNull()
        ->and($title)->not->toBeNull()
        ->and($instruction)->not->toBeNull()
        ->and($selectedLabel)->not->toBeNull()
        ->and($selectedLabel['text'])->toContain('Smart TV Auditorio')
        ->and($pairingDeviceId)->not->toBeNull()
        ->and($pairingDeviceId['type'])->toBe('input')
        ->and($pairingDeviceId['value'])->toBe((string) $device->id)
        ->and($pinInput)->not->toBeNull()
        ->and($pinInput['type'])->toBe('input')
        ->and($cancelBtn)->not->toBeNull()
        ->and($submitBtn)->not->toBeNull()
        ->and($submitBtn['action'])->toBe(Link::DEFAULT_SUBMIT_ACTION);
});

it('loads link device screen with select dropdown when no device is specified', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Sala 1']);

    $uiResponse = getScreenJson($this, Link::class);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $select = findComponentByName($payload, 'pairing_device_id');
    expect($select)->not->toBeNull()
        ->and($select['type'])->toBe('select');
});

it('pairs a device successfully via submit_approve_device_pairing action', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Sala 2']);

    $ui = uiScenario($this, Link::class, ['device_id' => $device->id, 'reset' => true]);

    $pairingManager = app(DevicePairingManager::class);
    $pairing = $pairingManager->initiate();
    $pin = $pairing['pin'];

    $response = $ui->action('btn_submit_pairing', 'submit_approve_device_pairing', [
        'pairing_device_id' => $device->id,
        'input_pin' => $pin,
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBeGreaterThan(0)
        ->and($device->pairing_pin)->toBe($pin);
});

it('rejects pairing with invalid pin length in Link screen', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Sala 3']);

    $ui = uiScenario($this, Link::class, ['device_id' => $device->id, 'reset' => true]);

    $response = $ui->action('btn_submit_pairing', 'submit_approve_device_pairing', [
        'pairing_device_id' => $device->id,
        'input_pin' => '12',
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBe(0);
});
