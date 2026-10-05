<?php

use App\Models\Device;
use App\UI\Screens\Device\EditDevice;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    UsimRole::firstOrCreate(['name' => 'smart_tv', 'guard_name' => 'device']);
    UsimRole::firstOrCreate(['name' => 'sensor', 'guard_name' => 'device']);
});

it('loads edit device screen in create mode with expected base components', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $uiResponse = getScreenJson($this, EditDevice::class);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $wrapper = findComponentByName($payload, 'edit_device_wrapper');
    $dialog = findComponentByName($payload, 'edit_device_dialog');
    $title = findComponentByName($payload, 'dialog_title');
    $deviceId = findComponentByName($payload, 'device_id');
    $deviceName = findComponentByName($payload, 'device_name');
    $deviceUnits = findComponentByName($payload, 'device_units');
    $deviceRoles = findComponentByName($payload, 'device_roles');
    $cancelBtn = findComponentByName($payload, 'btn_cancel_device');
    $saveBtn = findComponentByName($payload, 'btn_save_device');
    $deleteBtn = findComponentByName($payload, 'btn_delete_device');

    expect($wrapper)->not->toBeNull()
        ->and($dialog)->not->toBeNull()
        ->and($title)->not->toBeNull()
        ->and($deviceId)->not->toBeNull()
        ->and($deviceId['type'])->toBe('input')
        ->and($deviceId['value'])->toBe('')
        ->and($deviceName)->not->toBeNull()
        ->and($deviceName['type'])->toBe('input')
        ->and($deviceName['value'])->toBe('')
        ->and($deviceUnits)->not->toBeNull()
        ->and($deviceUnits['type'])->toBe('checkbox')
        ->and($deviceRoles)->not->toBeNull()
        ->and($deviceRoles['type'])->toBe('checkbox')
        ->and($cancelBtn)->not->toBeNull()
        ->and($cancelBtn['type'])->toBe('button')
        ->and($cancelBtn['action'])->toBe(EditDevice::DEFAULT_CANCEL_ACTION)
        ->and($saveBtn)->not->toBeNull()
        ->and($saveBtn['type'])->toBe('button')
        ->and($saveBtn['action'])->toBe(EditDevice::DEFAULT_SUBMIT_ACTION)
        ->and($deleteBtn)->toBeNull();
});

it('loads edit device screen in edit mode with device preloaded', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $deviceService = app(\App\Services\Device\DeviceService::class);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $device = $deviceService->createDevice([
        'name' => 'Sala de Reuniones TV',
        'unit_id' => $mainUnit->id,
        'roles' => ['smart_tv'],
    ]);

    $uiResponse = getScreenJson($this, EditDevice::class, ['id' => $device->id]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $deviceId = findComponentByName($payload, 'device_id');
    $deviceName = findComponentByName($payload, 'device_name');
    $deleteBtn = findComponentByName($payload, 'btn_delete_device');
    $statusBanner = findComponentByName($payload, 'device_status_banner');
    $pairBtn = findComponentByName($payload, 'btn_pair_this_device');

    expect($deviceId)->not->toBeNull()
        ->and($deviceId['value'])->toBe((string) $device->id)
        ->and($deviceName)->not->toBeNull()
        ->and($deviceName['value'])->toBe('Sala de Reuniones TV')
        ->and($deleteBtn)->not->toBeNull()
        ->and($statusBanner)->not->toBeNull()
        ->and($pairBtn)->not->toBeNull();
});

it('creates a new device directly through EditDevice screen action', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);

    $ui = uiScenario($this, EditDevice::class, ['reset' => true]);

    $response = $ui->action('btn_save_device', 'submit_save_device', [
        'device_name' => 'Tablet Guardia',
        'device_units' => [(string) $mainUnit->id],
        'device_roles' => ['sensor'],
    ]);
    $response->assertOk();

    $created = Device::where('name', 'Tablet Guardia')->first();
    expect($created)->not->toBeNull();
    expect($created->roles->pluck('name')->all())->toContain('sensor');
});

it('updates an existing device directly through EditDevice screen action', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $deviceService = app(\App\Services\Device\DeviceService::class);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $device = $deviceService->createDevice([
        'name' => 'Totem Original',
        'unit_id' => $mainUnit->id,
        'roles' => ['sensor'],
    ]);

    $ui = uiScenario($this, EditDevice::class, ['id' => $device->id, 'reset' => true]);

    $response = $ui->action('btn_save_device', 'submit_save_device', [
        'device_id' => $device->id,
        'device_name' => 'Totem Modificado',
        'device_units' => [(string) $mainUnit->id],
        'device_roles' => ['smart_tv'],
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->name)->toBe('Totem Modificado');
    expect($device->roles->pluck('name')->all())->toContain('smart_tv');
});

it('handles delete confirmation and execution in EditDevice screen', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Device To Delete']);

    $ui = uiScenario($this, EditDevice::class, ['id' => $device->id, 'reset' => true]);

    // Request deletion (opens ConfirmDialog)
    $response = $ui->action('btn_delete_device', 'delete_device', [
        'device_id' => $device->id,
    ]);
    $response->assertOk();

    // Confirm deletion
    $confirmResponse = $ui->action('btn_confirm', 'confirm_delete_device', [
        'device_id' => $device->id,
    ]);
    $confirmResponse->assertOk();

    expect(Device::find($device->id))->toBeNull();
});

it('handles unpairing a device in EditDevice screen', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Paired TV']);
    $device->createToken('test_token');

    expect($device->tokens()->count())->toBe(1);

    $ui = uiScenario($this, EditDevice::class, ['id' => $device->id, 'reset' => true]);

    $response = $ui->action('btn_unpair_device', 'unpair_device', [
        'device_id' => $device->id,
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBe(0);
});
