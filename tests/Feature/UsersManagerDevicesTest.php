<?php

use App\Models\Device;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Support\DevicePairingManager;

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
