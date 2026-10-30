<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\UI\Screens\Admin\AdminDevicePairingScreen;
use Idei\Usim\Screen;
use Idei\Usim\Support\DevicePairingManager;
use Idei\Usim\Support\UIIdGenerator;
use Tests\TestCase;

it('approves pairing when valid 4-digit pin is submitted via ApprovePairingRequest', function () {
    /** @var TestCase $this */
    $unit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'lobby']);
    setPermissionsTeamId($unit->id);

    $admin = User::factory()->create();
    $admin->usimUnits()->sync([$unit->id]);
    $perm = \Spatie\Permission\Models\Permission::findOrCreate('admin.admin_device_pairing.access', 'web');
    $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $roleAdmin->givePermissionTo($perm);
    $admin->assignRole($roleAdmin);
    $this->actingAs($admin);

    $device = Device::create([
        'name' => 'Conference TV',
        'device_identifier' => 'test-device-uuid',
        'roles' => ['kiosk'],
    ]);

    /** @var DevicePairingManager $manager */
    $manager = app(DevicePairingManager::class);
    $session = $manager->initiate();
    $pin = $session['pin'];

    $screen = Screen::make(AdminDevicePairingScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(AdminDevicePairingScreen::class, 'btn_submit');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'approve_pairing',
        'parameters' => [
            'input_pin' => $pin,
        ],
    ]);

    $response->assertOk();
    $diff = $response->json();
    expect($diff)->toHaveKey('toast');
    expect($diff['toast']['type'])->toBe('success');
    expect($diff['toast']['message'])->toBe('Emparejamiento exitoso.');
});

it('rejects invalid pin and auto-binds error on input_pin component via ApprovePairingRequest', function () {
    /** @var TestCase $this */
    $unit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'lobby']);
    setPermissionsTeamId($unit->id);

    $admin = User::factory()->create();
    $admin->usimUnits()->sync([$unit->id]);
    $perm = \Spatie\Permission\Models\Permission::findOrCreate('admin.admin_device_pairing.access', 'web');
    $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $roleAdmin->givePermissionTo($perm);
    $admin->assignRole($roleAdmin);
    $this->actingAs($admin);

    $screen = Screen::make(AdminDevicePairingScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(AdminDevicePairingScreen::class, 'btn_submit');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'approve_pairing',
        'parameters' => [
            'input_pin' => '12', // invalid (must be 4 digits)
        ],
    ]);

    $response->assertOk();
    $diff = $response->json();

    $inputId = UIIdGenerator::generateFromName(AdminDevicePairingScreen::class, 'input_pin');
    expect($diff)->toHaveKey((string) $inputId);
    expect($diff[(string) $inputId])->toHaveKey('error');
    expect($diff[(string) $inputId]['error'])->toBe('El PIN debe tener exactamente 4 dígitos.');
});
