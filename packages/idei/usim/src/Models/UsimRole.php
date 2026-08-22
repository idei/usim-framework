<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $home_screen
 * @property int $priority
 */
class UsimRole extends SpatieRole
{
    protected const DEFAULT_HOME_SCREEN = 'home';

    // Estos atributos virtuales se añadirán al serializar el modelo (Array/JSON)
    /** @var list<string> */
    protected $appends = ['home_screen', 'priority'];

    /**
     * Relación real One-to-One con tu tabla de configuraciones avanzadas
     */
    /**
     * @return HasOne<UsimRoleSetting, $this>
     */
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
                    'home_screen' => self::DEFAULT_HOME_SCREEN,
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
     * Helper de conveniencia que crea el rol y actualiza sus configuraciones extendidas
     */
    public static function createWithHome(string $name, string $homeScreenSlug, int $priority = 100, string $guardName = 'web'): self
    {
        /** @var self $role */
        $role = static::create([
            'name' => $name,
            'guard_name' => $guardName
        ]);

        $role->usimSetting()->update([
            'home_screen' => $homeScreenSlug,
            'priority' => $priority
        ]);

        return $role;
    }

    // =====================
    // ACCESSORS & MUTATORS
    // =====================

    /** @return Attribute<string, string> */
    protected function homeScreen(): Attribute
    {
        return Attribute::make(
            get: function () {
                /** @var UsimRoleSetting|null $setting */
                $setting = $this->relationLoaded('usimSetting')
                    ? $this->getRelation('usimSetting')
                    : $this->usimSetting()->first();
                return $setting->home_screen ?? config('usim.default_home_screen', self::DEFAULT_HOME_SCREEN);
            },
            set: fn($value) => $this->usimSetting()->updateOrCreate([], ['home_screen' => $value]),
        );
    }

    /** @return Attribute<int, int> */
    protected function priority(): Attribute
    {
        return Attribute::make(
            get: function () {
                /** @var UsimRoleSetting|null $setting */
                $setting = $this->relationLoaded('usimSetting')
                    ? $this->getRelation('usimSetting')
                    : $this->usimSetting()->first();

                $defaultPriority = config('usim.default_priority', 100);
                if (!is_int($defaultPriority)) {
                    $defaultPriority = 100;
                }

                return $setting->priority ?? $defaultPriority;
            },
            set: fn($value) => $this->usimSetting()->updateOrCreate([], ['priority' => (int) $value]),
        );
    }
}
