<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;

class UsimPermission extends SpatiePermission
{
    // Atributos virtuales mapeados automáticamente al serializar a JSON/Array
    protected $appends = ['display_name', 'description', 'metadata'];

    /**
     * Relación real One-to-One con tu tabla de configuraciones de permisos
     */
    public function usimSetting(): HasOne
    {
        return $this->hasOne(UsimPermissionSetting::class, 'permission_id');
    }

    /**
     * Ciclo de vida del modelo protegido por transacciones
     */
    protected static function booted(): void
    {
        static::creating(function (UsimPermission $permission) {
            DB::beginTransaction();
        });

        // Aseguramos la existencia del registro en la tabla secundaria con valores base
        static::created(function (UsimPermission $permission) {
            try {
                $permission->usimSetting()->create([
                    'display_name' => null,
                    'description'  => null,
                    'metadata'     => null,
                ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }

    /**
     * Helper ultra-defensivo adaptado para soportar configuraciones complejas en el Seeder
     */
    public static function createWithDescription(string $name, mixed $description = null, string $guardName = 'web'): self
    {
        // 1. Esto dispara el evento 'created' generando la fila con valores null de forma segura
        /** @var self $permission */
        $permission = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        // 2. Procesamos el payload de forma defensiva por si proviene de un mapeo de config complejo
        $finalDescription = null;
        $finalDisplayName = null;
        $finalMetadata = null;

        if (is_string($description)) {
            $finalDescription = $description;
        } elseif (is_array($description)) {
            $finalDescription = $description['description'] ?? $description['translation'] ?? null;
            $finalDisplayName = $description['display_name'] ?? null;
            $finalMetadata    = $description['metadata'] ?? null;
        }

        // 3. Si se extrajo información, actualizamos el registro base que ya existe obligatoriamente
        $updatePayload = array_filter([
            'description'  => $finalDescription,
            'display_name' => $finalDisplayName,
            'metadata'     => $finalMetadata ? (is_array($finalMetadata) ? json_encode($finalMetadata) : $finalMetadata) : null,
        ], fn($value) => $value !== null);

        if (!empty($updatePayload)) {
            $permission->usimSetting()->update($updatePayload);
        }

        return $permission;
    }

    // =====================
    // ACCESSORS & MUTATORS
    // =====================

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->usimSetting?->display_name,
            set: fn($value) => $this->usimSetting()->updateOrCreate([], ['display_name' => $value]),
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->usimSetting?->description,
            set: fn($value) => $this->usimSetting()->updateOrCreate([], ['description' => $value]),
        );
    }

    protected function metadata(): Attribute
    {
        return Attribute::make(
            get: function () {
                $meta = $this->usimSetting?->metadata;
                return is_string($meta) ? json_decode($meta, true) : $meta;
            },
            set: fn($value) => $this->usimSetting()->updateOrCreate([], [
                'metadata' => is_array($value) ? json_encode($value) : $value
            ]),
        );
    }
}
