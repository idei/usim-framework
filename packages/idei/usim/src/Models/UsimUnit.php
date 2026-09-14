<?php

namespace Idei\Usim\Models;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class UsimUnit extends Model
{
    /**
     * Los atributos que son asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'type',
        'parent_id',
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(UsimUnit::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(UsimUnit::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'actor', 'usim_unit_actors')->withTimestamps();
    }

    public function devices(): MorphToMany
    {
        return $this->morphedByMany(Device::class, 'actor', 'usim_unit_actors')->withTimestamps();
    }

    /**
     * Helper para obtener la clave de traducción autogenerada por el comando usim:sync
     * Por ejemplo, si el slug es 'software_dev', devolverá 'unit.software_dev.display_name'
     */
    public function getTranslationKeyAttribute(): string
    {
        return "unit.{$this->slug}.display_name";
    }

    /**
     * Helper para obtener el nombre traducido directamente.
     */
    public function getDisplayNameAttribute(): string
    {
        return trans($this->translation_key);
    }
}
