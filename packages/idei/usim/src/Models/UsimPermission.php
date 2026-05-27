<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;

class UsimPermission extends SpatiePermission
{
    /**
     * Relación real One-to-One con tu tabla de descripciones
     */
    public function usimSetting(): HasOne
    {
        return $this->hasOne(UsimPermissionSetting::class);
    }

    /**
     * El "Hook" del ciclo de vida del modelo para asegurar la creación del setting.
     */
    protected static function booted(): void
    {
        static::creating(function (UsimPermission $permission) {
            DB::beginTransaction();
        });

        // Este evento asegura que SIEMPRE existan los settings por defecto (incluso null)
        static::created(function (UsimPermission $permission) {
            try {
                $permission->usimSetting()->create([
                    'description' => null,
                ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }

    /**
     * Helper para crear el permiso y actualizar su descripción de un solo golpe
     */
    public static function createWithDescription(string $name, ?string $description = null, string $guardName = 'web'): self
    {
        // 1. Esto dispara internamente el 'booted' y crea el permiso + sus settings nulos
        /** @var self $permission */
        $permission = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        // 2. Como los settings YA EXISTEN obligatoriamente, simplemente los actualizamos
        if ($description !== null) {
            $permission->usimSetting()->update([
                'description' => $description
            ]);
        }

        return $permission;
    }
}
