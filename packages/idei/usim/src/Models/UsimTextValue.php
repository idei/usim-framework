<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $text_key_id
 * @property int $language_id
 * @property string|null $text_value
 * @property bool $needs_review
 * @property string|null $media_url
 * @property array<string, mixed>|null $media_meta
 * @property-read UsimTextKey|null $textKey
 * @property-read UsimLanguage|null $language
 */
class UsimTextValue extends Model
{
    protected $table = 'usim_text_values';

    protected $fillable = [
        'text_key_id',
        'language_id',
        'text_value',
        'needs_review',
        'media_url',
        'media_meta',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'media_meta' => 'array',
    ];

    /**
     * @return BelongsTo<UsimTextKey, $this>
     */
    public function textKey(): BelongsTo
    {
        return $this->belongsTo(UsimTextKey::class, 'text_key_id');
    }

    /**
     * @return BelongsTo<UsimLanguage, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(UsimLanguage::class, 'language_id');
    }

    /**
     * @param Builder<UsimTextValue> $query
     * @return Builder<UsimTextValue>
     */
    public function scopeWithLanguage(Builder $query, string $languageCode): Builder
    {
        return $query->whereHas('language', function (Builder $languageQuery) use ($languageCode): void {
            $languageQuery->where('code', $languageCode);
        });
    }
}
