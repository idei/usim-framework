<?php
// @usim: feature="admin", type="model"
namespace App\Models;

use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens; // Lo dejamos preparado para el Token definitivo

class Device extends Authenticatable
{
    use HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'pairing_pin',
        'device_token',
        'specs',
    ];

    protected $hidden = [
        'pairing_pin',
        'device_token',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
        ];
    }

    /**
     * Units that the device belongs to.
     *
     * @return MorphToMany<UsimUnit, $this>
     */
    public function usimUnits(): MorphToMany
    {
        return $this->morphToMany(UsimUnit::class, 'actor', 'usim_unit_actors')->withTimestamps();
    }
}
