<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class DeviceSyncService
{
    /**
     * @param array<string, array<string, mixed>> $devicesConfig
     * @return array{synced: list<string>, errors: list<string>}
     */
    public function sync(array $devicesConfig): array
    {
        $results = ['synced' => [], 'errors' => []];

        if (empty($devicesConfig)) {
            return $results;
        }

        // 1. Resolvemos el nombre de la clase dinámicamente
        // Si el desarrollador no lo definió en config, asumimos el path por defecto
        $deviceClass = config('usim.models.device', '\\App\\Models\\Device');
        $deviceClass = is_string($deviceClass) ? $deviceClass : '\\App\\Models\\Device';

        // 2. Verificamos la existencia para evitar un Fatal Error
        if (!class_exists($deviceClass)) {
            $results['errors'][] = "El modelo [{$deviceClass}] no existe. Asegúrate de ejecutar 'php artisan usim:install' primero.";
            return $results;
        }

        DB::beginTransaction();
        try {
            foreach ($devicesConfig as $deviceSlug => $data) {
                $deviceName = $this->normalizeStringValue($data['name'] ?? $deviceSlug, (string) $deviceSlug);

                // 3. Invocación estática dinámica
                /** @var class-string $deviceClass */
                $device = $deviceClass::updateOrCreate(
                    ['name' => $deviceName],
                    ['specs' => is_array($data['specs'] ?? null) ? $data['specs'] : null]
                );

                if (isset($data['unit_roles']) && is_array($data['unit_roles'])) {
                    foreach ($data['unit_roles'] as $unitSlug => $roles) {
                        if (!is_string($unitSlug)) {
                            continue;
                        }

                        $unit = UsimUnit::where('slug', $unitSlug)->first();

                        if (!$unit) {
                            $results['errors'][] = "Unidad '{$unitSlug}' no encontrada para '{$deviceName}'.";
                            continue;
                        }

                        $device->usimUnits()->syncWithoutDetaching([$unit->id]);

                        setPermissionsTeamId($unit->id);

                        if (!is_array($roles)) {
                            continue;
                        }

                        foreach ($roles as $roleNameValue) {
                            $roleName = $this->normalizeStringValue($roleNameValue, '');
                            if ($roleName === '') {
                                continue;
                            }

                            $roleExists = Role::where('name', $roleName)
                                ->where('guard_name', 'device')
                                ->exists();

                            if ($roleExists) {
                                $device->assignRole($roleName);
                            } else {
                                $results['errors'][] = "Rol '{$roleName}' inexistente para guard 'device'.";
                            }
                        }
                    }
                }

                $results['synced'][] = $deviceName;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = "Error crítico: " . $e->getMessage();
        }

        return $results;
    }

    private function normalizeStringValue(mixed $value, string $fallback): string
    {
        $normalized = is_scalar($value) || $value instanceof \Stringable ? (string) $value : $fallback;
        $trimmed = trim($normalized);

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
