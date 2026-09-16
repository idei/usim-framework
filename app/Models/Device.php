<?php
// @usim: feature="admin", type="model"
namespace App\Models;

use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens; // Lo dejamos preparado para el Token definitivo

/**
 * @property int $id
 * @property string $name
 * @property string|null $pairing_pin
 * @property string|null $device_token
 * @property array<string, mixed>|null $specs
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Idei\Usim\Models\UsimRole> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UsimUnit> $usimUnits
 */
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
