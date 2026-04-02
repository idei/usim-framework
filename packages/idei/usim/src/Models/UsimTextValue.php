<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsimTextValue extends Model
{
    protected $table = 'usim_text_values';

    protected $fillable = [
        'text_key_id',
        'language_id',
        'text_value',
        'media_url',
        'media_meta',
    ];

    protected $casts = [
        'media_meta' => 'array',
    ];

    public function textKey(): BelongsTo
    {
        return $this->belongsTo(UsimTextKey::class, 'text_key_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(UsimLanguage::class, 'language_id');
    }

    public function scopeWithLanguage(Builder $query, string $languageCode): Builder
    {
        return $query->whereHas('language', function (Builder $languageQuery) use ($languageCode): void {
            $languageQuery->where('code', $languageCode);
        });
    }
}
