<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

class UsimRole extends SpatieRole
{

    protected $appends = ['home_screen', 'priority'];

    public function usimSetting(): HasOne
    {
        return $this->hasOne(UsimRoleSetting::class, 'role_id');
    }

    protected static function booted(): void
    {
        static::creating(function (UsimRole $role) {
            DB::beginTransaction();
        });

        // Este evento asegura que SIEMPRE existan los settings por defecto
        static::created(function (UsimRole $role) {
            try {
                $role->usimSetting()->create([
                    'home_screen' => 'home',
                    'priority' => 100,
                ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }

    /**
     * El método sobrevive, pero ahora es un "Helper de conveniencia"
     * que actualiza el registro que el evento 'created' ya aseguró.
     */
    public static function createWithHome(string $name, string $homeScreenSlug, int $priority = 100, string $guardName = 'web'): self
    {
        // 1. Esto dispara internamente el 'booted' y crea el rol + sus settings por defecto
        /** @var self $role */
        $role = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        // 2. Como los settings YA EXISTEN obligatoriamente, simplemente los actualizamos
        $role->usimSetting()->update([
            'home_screen' => $homeScreenSlug,
            'priority' => $priority
        ]);

        return $role;
    }

    /**
     * Acceso directo a la Screen Home: $role->home_screen
     */
    protected function homeScreen(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->usimSetting?->home_screen ?? config('usim.default_home_screen', 'welcome'),
        );
    }

    /**
     * Acceso directo a la Prioridad: $role->priority
     */
    protected function priority(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->usimSetting?->priority ?? 100,
        );
    }
}
