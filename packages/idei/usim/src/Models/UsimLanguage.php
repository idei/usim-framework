<?php

namespace Idei\Usim\Models;

use Idei\Usim\Models\UsimTextValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsimLanguage extends Model
{
    protected $table = 'usim_languages';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active',
        'is_fallback',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_fallback' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(UsimTextValue::class, 'language_id');
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}
