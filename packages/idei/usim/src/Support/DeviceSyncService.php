<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class DeviceSyncService
{
    public function sync(array $devicesConfig): array
    {
        $results = ['synced' => [], 'errors' => []];

        if (empty($devicesConfig)) {
            return $results;
        }

        // 1. Resolvemos el nombre de la clase dinámicamente
        // Si el desarrollador no lo definió en config, asumimos el path por defecto
        $deviceClass = config('usim.models.device', '\\App\\Models\\Device');

        // 2. Verificamos la existencia para evitar un Fatal Error
        if (!class_exists($deviceClass)) {
            $results['errors'][] = "El modelo [{$deviceClass}] no existe. Asegúrate de ejecutar 'php artisan usim:install' primero.";
            return $results;
        }

        DB::beginTransaction();
        try {
            foreach ($devicesConfig as $deviceSlug => $data) {
                // 3. Invocación estática dinámica
                $device = $deviceClass::updateOrCreate(
                    ['name' => $data['name']],
                    ['specs' => $data['specs'] ?? null]
                );

                if (isset($data['unit_roles']) && is_array($data['unit_roles'])) {
                    foreach ($data['unit_roles'] as $unitSlug => $roles) {
                        $unit = UsimUnit::where('slug', $unitSlug)->first();

                        if (!$unit) {
                            $results['errors'][] = "Unidad '{$unitSlug}' no encontrada para '{$data['name']}'.";
                            continue;
                        }

                        $device->usimUnits()->syncWithoutDetaching([$unit->id]);

                        setPermissionsTeamId($unit->id);

                        foreach ($roles as $roleName) {
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

                $results['synced'][] = $data['name'];
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = "Error crítico: " . $e->getMessage();
        }

        return $results;
    }
}
