<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Contracts\PairableActorInterface;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use ReflectionException;
use ReflectionProperty;

class DeviceSecurityGuard implements DeviceSecurityGuardInterface
{
    /**
     * Resolve the device actor associated with the given request.
     */
    public function resolveDevice(Request $request): ?PairableActorInterface
    {
        // 1. Check if device guard already has an authenticated PairableActorInterface
        $user = Auth::guard('device')->user();
        if ($user instanceof PairableActorInterface) {
            return $user;
        }

        // 2. Check token from request storage or bearer header
        $token = $this->extractToken($request);
        if ($token !== null && $token !== '') {
            $device = $this->resolveDeviceByToken($token);
            if ($device !== null) {
                return $device;
            }
        }

        // 3. Check request user resolver
        $reqUser = $request->user();
        if ($reqUser instanceof PairableActorInterface) {
            return $reqUser;
        }

        return null;
    }

    /**
     * Resolve a device actor given an access token string.
     */
    public function resolveDeviceByToken(string $token): ?PairableActorInterface
    {
        if (! class_exists(PersonalAccessToken::class)) {
            return null;
        }

        $tokenModel = PersonalAccessToken::findToken($token);
        if ($tokenModel !== null && $tokenModel->tokenable instanceof PairableActorInterface) {
            return $tokenModel->tokenable;
        }

        return null;
    }

    /**
     * Determine if the device associated with the request is currently paired and active.
     */
    public function isDevicePaired(Request $request): bool
    {
        $device = $this->resolveDevice($request);

        return $device !== null && $device->isPaired();
    }

    /**
     * Determine if kiosk mode is currently active.
     */
    public function isKioskModeEnabled(): bool
    {
        $deviceUser = Auth::guard('device')->user();
        if ($deviceUser instanceof PairableActorInterface && $deviceUser->isPaired()) {
            return true;
        }

        return (bool) config('usim.kiosk_mode', false);
    }

    /**
     * Authenticate the given device in the device guard and request.
     */
    public function authenticateDevice(PairableActorInterface $device, ?Request $request = null): void
    {
        if (! ($device instanceof Authenticatable)) {
            return;
        }

        $guard = Auth::guard('device');
        $guard->login($device);

        if ($guard instanceof SessionGuard) {
            try {
                $loggedOutProp = new ReflectionProperty($guard, 'loggedOut');
                $loggedOutProp->setAccessible(true);
                $loggedOutProp->setValue($guard, false);
            } catch (ReflectionException) {
                // Ignore reflection errors
            }
        }

        if ($request !== null) {
            $request->setUserResolver(fn () => $device);
        }
    }

    /**
     * Logout and invalidate the device guard session.
     */
    public function logoutDevice(): void
    {
        $guard = Auth::guard('device');

        if ($guard instanceof SessionGuard) {
            $guard->forgetUser();
            try {
                $loggedOutProp = new ReflectionProperty($guard, 'loggedOut');
                $loggedOutProp->setAccessible(true);
                $loggedOutProp->setValue($guard, true);
            } catch (ReflectionException) {
                // Ignore reflection errors
            }
        } elseif (method_exists($guard, 'forgetUser')) {
            $guard->forgetUser();
        }
    }

    /**
     * Extract token from request storage or Bearer header.
     */
    protected function extractToken(Request $request): ?string
    {
        $storage = $request->input('storage');
        if (is_array($storage) && ! empty($storage['store_token']) && is_string($storage['store_token'])) {
            return $storage['store_token'];
        }

        $bearer = $request->bearerToken();
        if (! empty($bearer)) {
            return $bearer;
        }

        return null;
    }
}
