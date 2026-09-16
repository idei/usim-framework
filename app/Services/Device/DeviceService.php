<?php

// @usim: feature="admin", type="service"

namespace App\Services\Device;

use App\Models\Device;
use Idei\Usim\Support\DevicePairingManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DeviceService
{
    public function __construct(
        protected ?DevicePairingManager $pairingManager = null,
    ) {
        $this->pairingManager = $pairingManager ?? app(DevicePairingManager::class);
    }

    /**
     * @param array{
     *     name: string,
     *     roles?: list<string>|string,
     *     unit_id?: int|string|null,
     *     specs?: array<string, mixed>|null
     * } $data
     */
    public function createDevice(array $data): Device
    {
        $device = Device::create([
            'name' => $data['name'],
            'specs' => $data['specs'] ?? null,
        ]);

        if (!empty($data['roles'])) {
            $roles = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
            $device->syncRoles($roles);
        }

        if (!empty($data['unit_id'])) {
            $device->usimUnits()->sync([(int) $data['unit_id']]);
        }

        return $device;
    }

    /**
     * @param int|string $id
     * @param array{
     *     name?: string,
     *     roles?: list<string>|string,
     *     unit_id?: int|string|null,
     *     specs?: array<string, mixed>|null
     * } $data
     */
    public function updateDevice(int|string $id, array $data): ?Device
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return null;
        }

        if (isset($data['name'])) {
            $device->name = $data['name'];
        }

        if (array_key_exists('specs', $data)) {
            $device->specs = $data['specs'];
        }

        $device->save();

        if (isset($data['roles'])) {
            $roles = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
            $device->syncRoles($roles);
        }

        if (array_key_exists('unit_id', $data)) {
            if ($data['unit_id'] !== null) {
                $device->usimUnits()->sync([(int) $data['unit_id']]);
            } else {
                $device->usimUnits()->detach();
            }
        }

        return $device;
    }

    public function deleteDevice(int|string $id): bool
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return false;
        }

        $device->tokens()->delete();
        $device->usimUnits()->detach();

        return (bool) $device->delete();
    }

    public function unpairDevice(int|string $id): bool
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return false;
        }

        $device->tokens()->delete();
        $device->device_token = null;
        $device->pairing_pin = null;
        $device->save();

        return true;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function pairDevice(int|string $id, string $pin): array
    {
        $cleanPin = trim($pin);
        if (strlen($cleanPin) !== 4) {
            return [
                'success' => false,
                'message' => t('screen.admin.users_manager.device_pin_length_error'),
            ];
        }

        $device = $this->getDevice($id);
        if (!$device) {
            return [
                'success' => false,
                'message' => 'Dispositivo no encontrado.',
            ];
        }

        $manager = $this->pairingManager ?? app(DevicePairingManager::class);
        $success = $manager->approve($cleanPin, $device);

        if ($success) {
            $device->pairing_pin = $cleanPin;
            if (empty($device->device_token)) {
                $device->device_token = Str::random(60);
            }
            $device->save();

            return [
                'success' => true,
                'message' => t('screen.admin.users_manager.device_paired_success'),
            ];
        }

        return [
            'success' => false,
            'message' => t('screen.admin.users_manager.device_invalid_pin'),
        ];
    }

    public function getDevice(int|string $id): ?Device
    {
        return Device::with(['tokens', 'roles', 'usimUnits'])->find($id);
    }

    /**
     * @return Collection<int, Device>
     */
    public function getAllDevices(): Collection
    {
        return Device::with(['tokens', 'roles', 'usimUnits'])->orderBy('name')->get();
    }
}

