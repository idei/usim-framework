<?php

use App\Models\Device;
use App\Services\Device\DeviceListingService;
use App\Services\Device\DeviceService;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\DevicePairingManager;

beforeEach(function () {
    $prev = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId(null);
    }
    UsimRole::firstOrCreate(['name' => 'smart_tv', 'guard_name' => 'device']);
    UsimRole::firstOrCreate(['name' => 'sensor', 'guard_name' => 'device']);
    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId($prev);
    }
});

it('renders devices tab and table in users manager', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $devicesTab = $ui->component('devices_crud_container');
    expect($devicesTab)->not->toBeNull();

    $devicesTable = $ui->component('devices_table');
    expect($devicesTable)->not->toBeNull();

    $ui->assertNoIssues();
});

it('filters devices by search term', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    Device::create(['name' => 'Smart TV Sala Principal']);
    Device::create(['name' => 'Tablet Recepcion']);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->input('search_devices', 'search_devices', [
        'value' => 'Smart TV',
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();

    $ui->assertNoIssues();
});

it('opens create device modal when clicking add device button', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('add_device_btn', 'add_device_clicked', []);
    $response->assertOk();

    $payload = $response->json();
    expect(findComponentByName($payload, 'edit_device_dialog'))->not->toBeNull();
    expect(findComponentByName($payload, 'device_name'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_save_device'))->not->toBeNull();
});

it('opens edit device modal when clicking device table row', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Monitor Cocina']);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('devices_table', 'devices_table_row_clicked', [
        'model_id' => $device->id,
    ]);
    $response->assertOk();

    $payload = $response->json();
    expect(findComponentByName($payload, 'edit_device_dialog'))->not->toBeNull();
    expect(findComponentByName($payload, 'device_id'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_delete_device'))->not->toBeNull();
});

it('opens pair device modal when clicking pair button', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Totem Acceso']);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('pair_device_btn', 'pair_device_clicked', [
        'device_id' => $device->id,
    ]);
    $response->assertOk();

    $payload = $response->json();
    expect(findComponentByName($payload, 'device_pairing_dialog'))->not->toBeNull();
    expect(findComponentByName($payload, 'input_pin'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_submit_pairing'))->not->toBeNull();
});

it('creates and updates device via modal submit', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open create modal
    $ui->action('add_device_btn', 'add_device_clicked', []);

    // Create device
    $response = $ui->action('btn_save_device', 'submit_save_device', [
        'device_name' => 'Sensor Temperatura Sala A',
    ]);
    $response->assertOk();

    $device = Device::where('name', 'Sensor Temperatura Sala A')->first();
    expect($device)->not->toBeNull();

    // Open edit modal for the newly created device
    $ui->action('devices_table', 'devices_table_row_clicked', [
        'model_id' => $device->id,
    ]);

    // Update device
    $updateResponse = $ui->action('btn_save_device', 'submit_save_device', [
        'device_id' => $device->id,
        'device_name' => 'Sensor Temperatura Sala A (Actualizado)',
    ]);
    $updateResponse->assertOk();

    $device->refresh();
    expect($device->name)->toBe('Sensor Temperatura Sala A (Actualizado)');
});

it('pairs a device successfully using PIN workflow', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Auditorio']);

    // Simulate client device initiating pairing
    $pairingManager = app(DevicePairingManager::class);
    $pairing = $pairingManager->initiate();
    $pin = $pairing['pin'];

    expect(strlen($pin))->toBe(4);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open pair modal
    $ui->action('pair_device_btn', 'pair_device_clicked', [
        'device_id' => $device->id,
    ]);

    // Admin submits the PIN to approve pairing
    $response = $ui->action('btn_submit_pairing', 'submit_approve_device_pairing', [
        'pairing_device_id' => $device->id,
        'input_pin' => $pin,
    ]);
    $response->assertOk();

    $device->refresh();
    // Device now has tokens and is paired
    expect($device->tokens()->count())->toBeGreaterThan(0);
    expect($device->pairing_pin)->toBe($pin);
});

it('rejects pairing with invalid or wrong PIN', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Smart TV Auditorio']);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open pair modal
    $ui->action('pair_device_btn', 'pair_device_clicked', [
        'device_id' => $device->id,
    ]);

    $response = $ui->action('btn_submit_pairing', 'submit_approve_device_pairing', [
        'pairing_device_id' => $device->id,
        'input_pin' => '0000',
    ]);
    $response->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBe(0);
});

it('unpairs and deletes a device', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $device = Device::create(['name' => 'Dispositivo de Prueba']);
    $device->createToken('test_token');

    expect($device->tokens()->count())->toBe(1);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open edit modal for the device
    $ui->action('devices_table', 'devices_table_row_clicked', [
        'model_id' => $device->id,
    ]);

    // Unpair
    $unpairResponse = $ui->action('btn_unpair_device', 'unpair_device', [
        'device_id' => $device->id,
    ]);
    $unpairResponse->assertOk();

    $device->refresh();
    expect($device->tokens()->count())->toBe(0);

    // Click delete device button from edit modal
    $deleteModalResponse = $ui->action('btn_delete_device', 'delete_device', [
        'device_id' => $device->id,
    ]);
    $deleteModalResponse->assertOk();

    // Confirm delete
    $deleteResponse = $ui->action('btn_confirm', 'confirm_delete_device', [
        'device_id' => $device->id,
    ]);
    $deleteResponse->assertOk();

    expect(Device::find($device->id))->toBeNull();
});

it('isolates devices by active unit and includes institutional devices', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $ingeoUnit = UsimUnit::firstOrCreate(['slug' => 'ingeo'], ['type' => 'institute']);

    $deviceService = app(DeviceService::class);
    $deviceListing = app(DeviceListingService::class);

    $tvIdei = $deviceService->createDevice([
        'name' => 'TV Consulta Idei',
        'unit_id' => $ideiUnit->id,
        'roles' => ['smart_tv'],
    ]);

    $sensorIngeo = $deviceService->createDevice([
        'name' => 'Sensor Entrada Ingeo',
        'unit_id' => $ingeoUnit->id,
        'roles' => ['sensor'],
    ]);

    $totemMain = $deviceService->createDevice([
        'name' => 'Totem Campus Principal',
        'unit_id' => $mainUnit->id,
        'roles' => ['smart_tv'],
    ]);

    // Active context: Idei
    setPermissionsTeamId($ideiUnit->id);
    session()->put('current_unit_id', $ideiUnit->id);
    $deviceListing->setUnitContext($ideiUnit->id);

    $ideiDevices = collect($deviceListing->all());
    $ideiDeviceIds = $ideiDevices->pluck('id')->all();

    expect($ideiDeviceIds)->toContain($tvIdei->id);
    expect($ideiDeviceIds)->toContain($totemMain->id);
    expect($ideiDeviceIds)->not->toContain($sensorIngeo->id);

    // Active context: Ingeo
    setPermissionsTeamId($ingeoUnit->id);
    session()->put('current_unit_id', $ingeoUnit->id);
    $deviceListing->setUnitContext($ingeoUnit->id);

    $ingeoDevices = collect($deviceListing->all());
    $ingeoDeviceIds = $ingeoDevices->pluck('id')->all();

    expect($ingeoDeviceIds)->toContain($sensorIngeo->id);
    expect($ingeoDeviceIds)->toContain($totemMain->id);
    expect($ingeoDeviceIds)->not->toContain($tvIdei->id);
});

it('handles shared devices across multiple units', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $ingeoUnit = UsimUnit::firstOrCreate(['slug' => 'ingeo'], ['type' => 'institute']);
    $oafaUnit = UsimUnit::firstOrCreate(['slug' => 'oafa'], ['type' => 'institute']);

    $deviceService = app(DeviceService::class);
    $deviceListing = app(DeviceListingService::class);

    $sharedSensor = $deviceService->createDevice([
        'name' => 'Sensor Hall Interdepartamental',
        'unit_ids' => [$ingeoUnit->id, $oafaUnit->id],
        'roles' => ['sensor'],
    ]);

    expect($sharedSensor->isShared())->toBeTrue();
    expect($sharedSensor->isPublic())->toBeFalse();

    // Context: Ingeo -> Visible
    $deviceListing->setUnitContext($ingeoUnit->id);
    setPermissionsTeamId($ingeoUnit->id);
    expect(collect($deviceListing->all())->pluck('id'))->toContain($sharedSensor->id);

    // Context: Oafa -> Visible
    $deviceListing->setUnitContext($oafaUnit->id);
    setPermissionsTeamId($oafaUnit->id);
    expect(collect($deviceListing->all())->pluck('id'))->toContain($sharedSensor->id);

    // Context: Idei -> Not visible
    $deviceListing->setUnitContext($ideiUnit->id);
    setPermissionsTeamId($ideiUnit->id);
    expect(collect($deviceListing->all())->pluck('id'))->not->toContain($sharedSensor->id);
});

it('supports just-in-time device pairing and registration in one step', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ingeoUnit = UsimUnit::firstOrCreate(['slug' => 'ingeo'], ['type' => 'institute']);
    setPermissionsTeamId($ingeoUnit->id);
    session()->put('current_unit_id', $ingeoUnit->id);

    // Simulate device waiting with PIN
    $pairingManager = app(DevicePairingManager::class);
    $pairing = $pairingManager->initiate();
    $pin = $pairing['pin'];

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open pair dialog
    $ui->action('pair_device_btn', 'pair_device_clicked', []);

    // Admin submits pairing modal with "new" device data
    $response = $ui->action('btn_submit_pairing', 'submit_approve_device_pairing', [
        'pairing_device_id' => 'new',
        'new_device_name' => 'Tótem Entrada Ingeo JIT',
        'new_device_role' => 'smart_tv',
        'device_unit_id' => $ingeoUnit->id,
        'input_pin' => $pin,
    ]);
    $response->assertOk();

    $device = Device::where('name', 'Tótem Entrada Ingeo JIT')->first();
    expect($device)->not->toBeNull();
    expect($device->tokens()->count())->toBeGreaterThan(0);
    expect($device->pairing_pin)->toBe($pin);
    expect($device->usimUnits->pluck('id'))->toContain($ingeoUnit->id);
});

it('saves device with multiple units via modal submit', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $ingeoUnit = UsimUnit::firstOrCreate(['slug' => 'ingeo'], ['type' => 'institute']);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    // Open create modal
    $ui->action('add_device_btn', 'add_device_clicked', []);

    // Create device with multiple units
    $response = $ui->action('btn_save_device', 'submit_save_device', [
        'device_name' => 'Display Compartido Multi',
        'device_units' => [(string) $ideiUnit->id, (string) $ingeoUnit->id],
        'device_roles' => ['smart_tv'],
    ]);
    $response->assertOk();

    $device = Device::where('name', 'Display Compartido Multi')->first();
    expect($device)->not->toBeNull();
    expect($device->usimUnits->pluck('id')->all())->toContain($ideiUnit->id);
    expect($device->usimUnits->pluck('id')->all())->toContain($ingeoUnit->id);
});
