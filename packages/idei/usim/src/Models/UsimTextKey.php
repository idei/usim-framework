<?php

namespace Idei\Usim\Models;

use Idei\Usim\Models\UsimTextValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsimTextKey extends Model
{
    protected $table = 'usim_text_keys';

    protected $fillable = [
        'key',
        'group',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(UsimTextValue::class, 'text_key_id');
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
