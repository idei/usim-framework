<?php

namespace Idei\Usim\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for device authentication, pairing validation, and kiosk security enforcement.
 */
interface DeviceSecurityGuardInterface
{
    /**
     * Resolve the device actor associated with the given request.
     */
    public function resolveDevice(?Request $request = null): ?PairableActorInterface;

    /**
     * Resolve a device actor given an access token string.
     */
    public function resolveDeviceByToken(string $token): ?PairableActorInterface;

    /**
     * Determine if the device associated with the request is currently paired and active.
     */
    public function isDevicePaired(?Request $request = null): bool;

    /**
     * Determine if kiosk mode is currently active.
     */
    public function isKioskModeEnabled(): bool;

    /**
     * Authenticate the given device in the device guard and request.
     */
    public function authenticateDevice(PairableActorInterface $device, ?Request $request = null): void;

    /**
     * Logout and invalidate the device guard session.
     */
    public function logoutDevice(): void;
}
