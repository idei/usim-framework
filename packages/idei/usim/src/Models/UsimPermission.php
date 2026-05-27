<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Permission as SpatiePermission;

class UsimPermission extends SpatiePermission
{
    /**
     * Relación real One-to-One con tu tabla de descripciones
     */
    public function usimSetting(): HasOne
    {
        // Asumiendo que creas el modelo básico UsimPermissionSetting
        return $this->hasOne(UsimPermissionSetting::class);
    }

    /**
     * Helper para crear el permiso y su descripción de un solo golpe
     */
    public static function createWithDescription(string $name, ?string $description = null, string $guardName = 'web'): self
    {
        /** @var self $permission */
        $permission = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        $permission->usimSetting()->create([
            'description' => $description
        ]);

        return $permission;
    }
}
