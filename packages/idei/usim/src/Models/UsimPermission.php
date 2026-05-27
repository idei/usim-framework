<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;

class UsimPermission extends SpatiePermission
{
    public function usimSetting(): HasOne
    {
        return $this->hasOne(UsimPermissionSetting::class, 'permission_id');
    }

    protected static function booted(): void
    {
        static::creating(function (UsimPermission $permission) {
            DB::beginTransaction();
        });

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
     * Helper ultra-defensivo para el Framework
     */
    public static function createWithDescription(string $name, mixed $description = null, string $guardName = 'web'): self
    {
        // 1. Forzamos a que pase por nuestro ciclo de vida seguro
        /** @var self $permission */
        $permission = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        // 2. Procesamos la descripción de forma mega defensiva por si llega un array
        $finalDescription = null;

        if (\is_string($description)) {
            $finalDescription = $description;
        } elseif (\is_array($description)) {
            $finalDescription = $description['translation']
                ?? $description['description']
                ?? "Permission: $name";
        }

        if ($finalDescription !== null) {
            $permission->usimSetting()->update([
                'description' => $finalDescription
            ]);
        }

        return $permission;
    }
}
