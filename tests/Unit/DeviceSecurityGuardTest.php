<?php

use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Contracts\PairableActorInterface;
use Idei\Usim\Http\Middleware\PrepareUIContext;
use Idei\Usim\Support\DeviceSecurityGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FakeDeviceActor implements Authenticatable, PairableActorInterface
{
    public function __construct(
        public int $id = 1,
        public string $name = 'Test Kiosk Device',
        public bool $paired = true
    ) {}

    public function isPaired(): bool
    {
        return $this->paired;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}

it('resolves DeviceSecurityGuardInterface from service container', function () {
    $guard = app(DeviceSecurityGuardInterface::class);

    expect($guard)->toBeInstanceOf(DeviceSecurityGuardInterface::class)
        ->and($guard)->toBeInstanceOf(DeviceSecurityGuard::class);
});

it('resolves device from authenticated device guard', function () {
    $fakeDevice = new FakeDeviceActor(42, 'Living Room Kiosk', true);
    Auth::guard('device')->setUser($fakeDevice);

    $guard = app(DeviceSecurityGuardInterface::class);
    $request = Request::create('/api/ui/device/kiosk', 'GET');

    $resolved = $guard->resolveDevice($request);

    expect($resolved)->not->toBeNull()
        ->and($resolved)->toBeInstanceOf(PairableActorInterface::class)
        ->and($resolved->isPaired())->toBeTrue();
});

it('correctly evaluates isDevicePaired for paired and unpaired devices', function () {
    $guard = app(DeviceSecurityGuardInterface::class);
    $request = Request::create('/api/ui/device/kiosk', 'GET');

    // Case 1: No device
    Auth::guard('device')->forgetUser();
    expect($guard->isDevicePaired($request))->toBeFalse();

    // Case 2: Paired device
    $pairedDevice = new FakeDeviceActor(10, 'Paired Kiosk', true);
    Auth::guard('device')->setUser($pairedDevice);
    expect($guard->isDevicePaired($request))->toBeTrue();

    // Case 3: Unpaired device
    $unpairedDevice = new FakeDeviceActor(11, 'Unpaired Kiosk', false);
    Auth::guard('device')->setUser($unpairedDevice);
    expect($guard->isDevicePaired($request))->toBeFalse();
});

it('detects kiosk mode based on active paired device and config', function () {
    $guard = app(DeviceSecurityGuardInterface::class);

    // No device, config false
    Auth::guard('device')->forgetUser();
    config(['usim.kiosk_mode' => false]);
    expect($guard->isKioskModeEnabled())->toBeFalse();

    // Config true
    config(['usim.kiosk_mode' => true]);
    expect($guard->isKioskModeEnabled())->toBeTrue();

    // Reset config, paired device active
    config(['usim.kiosk_mode' => false]);
    $device = new FakeDeviceActor(5, 'Smart TV', true);
    Auth::guard('device')->setUser($device);
    expect($guard->isKioskModeEnabled())->toBeTrue();
});

it('authenticates device and updates request user resolver', function () {
    $guard = app(DeviceSecurityGuardInterface::class);
    $device = new FakeDeviceActor(77, 'Reception Kiosk', true);
    $request = Request::create('/api/ui/device/kiosk', 'GET');

    $guard->authenticateDevice($device, $request);

    expect(Auth::guard('device')->check())->toBeTrue()
        ->and(Auth::guard('device')->user())->toBe($device)
        ->and($request->user())->toBe($device);
});

it('logs out device from device guard session', function () {
    $guard = app(DeviceSecurityGuardInterface::class);
    $device = new FakeDeviceActor(99, 'Lobby Terminal', true);
    Auth::guard('device')->setUser($device);

    expect(Auth::guard('device')->check())->toBeTrue();

    $guard->logoutDevice();

    expect(Auth::guard('device')->check())->toBeFalse();
});

it('delegates device unpairing enforcement to DeviceSecurityGuardInterface in PrepareUIContext', function () {
    $fakeGuard = Mockery::mock(DeviceSecurityGuardInterface::class);
    $fakeGuard->shouldReceive('resolveDeviceByToken')->andReturn(null);
    $fakeGuard->shouldReceive('logoutDevice')->once();

    app()->instance(DeviceSecurityGuardInterface::class, $fakeGuard);

    $unpairedDevice = new FakeDeviceActor(88, 'Revoked Kiosk', false);
    Auth::guard('device')->setUser($unpairedDevice);

    $middleware = new PrepareUIContext;
    $request = Request::create('/api/ui/device/kiosk', 'GET');

    $middleware->handle($request, function (Request $req): JsonResponse {
        return response()->json(['user' => $req->user()]);
    });

    expect($request->user())->toBeNull();
});
