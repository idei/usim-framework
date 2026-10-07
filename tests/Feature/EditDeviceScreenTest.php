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
    $pairBtn = findComponentByName($payload, 'btn_pair_unpair_device');
    $statusBanner = findComponentByName($payload, 'device_status_banner');

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
        ->and($deleteBtn)->toBeNull()
        ->and($pairBtn)->toBeNull()
        ->and($statusBanner)->toBeNull();
});

it('loads edit device screen in edit mode with unpaired device', function () {
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
    $pairBtn = findComponentByName($payload, 'btn_pair_unpair_device');

    expect($deviceId)->not->toBeNull()
        ->and($deviceId['value'])->toBe((string) $device->id)
        ->and($deviceName)->not->toBeNull()
        ->and($deviceName['value'])->toBe('Sala de Reuniones TV')
        ->and($deleteBtn)->not->toBeNull()
        ->and($deleteBtn['enabled'])->toBeTrue()
        ->and($statusBanner)->not->toBeNull()
        ->and($statusBanner['style'])->toBe('warning')
        ->and($pairBtn)->not->toBeNull()
        ->and($pairBtn['action'])->toBe(EditDevice::PAIR_UNPAIR_ACTION)
        ->and($pairBtn['style'])->toBe('warning');
});

it('loads edit device screen in edit mode with paired device showing paired status and disabled delete', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $deviceService = app(\App\Services\Device\DeviceService::class);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $device = $deviceService->createDevice([
        'name' => 'Kiosco Central',
        'unit_id' => $mainUnit->id,
        'roles' => ['smart_tv'],
    ]);
    $device->createToken('test_token');

    $uiResponse = getScreenJson($this, EditDevice::class, ['id' => $device->id]);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $deleteBtn = findComponentByName($payload, 'btn_delete_device');
    $statusBanner = findComponentByName($payload, 'device_status_banner');
    $pairBtn = findComponentByName($payload, 'btn_pair_unpair_device');

    expect($statusBanner)->not->toBeNull()
        ->and($statusBanner['style'])->toBe('success')
        ->and($pairBtn)->not->toBeNull()
        ->and($pairBtn['action'])->toBe(EditDevice::PAIR_UNPAIR_ACTION)
        ->and($pairBtn['style'])->toBe('success')
        ->and($deleteBtn)->not->toBeNull()
        ->and($deleteBtn['enabled'])->toBeFalse();
});

it('creates a new device directly through EditDevice screen action', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);

    $ui = uiScenario($this, EditDevice::class, ['reset' => true]);

    $response = $ui->action('btn_save_device', EditDevice::DEFAULT_SUBMIT_ACTION, [
        'device_name' => 'Tablet Guardia',
        'device_units' => [(string) $mainUnit->id],
        'device_roles' => ['sensor'],
    ]);
    $response->assertOk();

    $created = Device::where('name', 'Tablet Guardia')->first();
    expect($created)->not->toBeNull();
    expect($created->roles->pluck('name')->all())->toContain('sensor');
});

it('validates required fields when saving device', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);

    $ui = uiScenario($this, EditDevice::class, ['reset' => true]);

    // Validation for empty name
    $response = $ui->action('btn_save_device', EditDevice::DEFAULT_SUBMIT_ACTION, [
        'device_name' => '',
        'device_units' => [(string) $mainUnit->id],
        'device_roles' => ['sensor'],
    ]);
    $response->assertOk();
    expect(Device::where('name', '')->first())->toBeNull();

    // Validation for empty roles
    $response = $ui->action('btn_save_device', EditDevice::DEFAULT_SUBMIT_ACTION, [
        'device_name' => 'Sin Rol',
        'device_units' => [(string) $mainUnit->id],
        'device_roles' => [],
    ]);
    $response->assertOk();
    expect(Device::where('name', 'Sin Rol')->first())->toBeNull();
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

    $response = $ui->action('btn_save_device', EditDevice::DEFAULT_SUBMIT_ACTION, [
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
    $response = $ui->action('btn_delete_device', EditDevice::DELETE_ACTION, [
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

    $response = $ui->action('btn_pair_unpair_device', EditDevice::PAIR_UNPAIR_ACTION, [
        'device_id' => $device->id,
        'is_paired' => true,
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBe(0);
});

it('opens link modal when pair button is clicked on an unpaired device', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Unpaired TV']);

    $ui = uiScenario($this, EditDevice::class, ['id' => $device->id, 'reset' => true]);

    $response = $ui->action('btn_pair_unpair_device', EditDevice::PAIR_UNPAIR_ACTION, [
        'device_id' => $device->id,
        'is_paired' => false,
    ]);
    $response->assertOk();

    $linkModal = findComponentByName($response->json(), 'app_ui_screens_device_link');
    $pinInput = findComponentByName($response->json(), 'input_pin');
    expect($linkModal)->not->toBeNull()
        ->and($linkModal['parent'])->toBe('modal')
        ->and($pinInput)->not->toBeNull();
});

it('dynamically updates UI state on device paired and unpaired events', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Living Room Device']);

    $ui = uiScenario($this, EditDevice::class, ['id' => $device->id, 'reset' => true]);

    // Initially unpaired
    $statusBanner = $ui->component('device_status_banner')->data();
    expect($statusBanner['style'])->toBe('warning');

    // Simulate device paired
    $device->createToken('test_token');
    $response = $ui->action('btn_pair_unpair_device', 'device_paired', [
        'device_id' => $device->id,
        'message' => 'Dispositivo vinculado con éxito',
    ]);
    $response->assertOk();

    $statusBanner = $ui->component('device_status_banner')->data();
    expect($statusBanner['style'])->toBe('success');
    $pairBtn = $ui->component('btn_pair_unpair_device')->data();
    expect($pairBtn['style'])->toBe('success');
    $deleteBtn = $ui->component('btn_delete_device')->data();
    expect($deleteBtn['enabled'])->toBeFalse();

    // Simulate device unpaired
    $device->tokens()->delete();
    $response = $ui->action('btn_pair_unpair_device', 'device_unpaired', [
        'device_id' => $device->id,
        'message' => 'Dispositivo desvinculado con éxito',
    ]);
    $response->assertOk();

    $statusBanner = $ui->component('device_status_banner')->data();
    expect($statusBanner['style'])->toBe('warning');
    $pairBtn = $ui->component('btn_pair_unpair_device')->data();
    expect($pairBtn['style'])->toBe('warning');
    $deleteBtn = $ui->component('btn_delete_device')->data();
    expect($deleteBtn['enabled'])->toBeTrue();
});
