<?php

namespace Idei\Usim\Support\Translation;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Models\UsimTextValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TranslationKeyManager
{
    public function upsertLanguage(
        string $code,
        string $name,
        ?string $nativeName = null,
        bool $isActive = true,
        bool $isFallback = false
    ): UsimLanguage {
        $language = UsimLanguage::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'native_name' => $nativeName,
                'is_active' => $isActive,
                'is_fallback' => $isFallback,
            ]
        );

        if ($isFallback) {
            UsimLanguage::query()
                ->where('id', '!=', $language->id)
                ->update(['is_fallback' => false]);
        }

        return $language;
    }

    public function createOrUpdateKey(string $key, array $attributes = []): UsimTextKey
    {
        $textKey = UsimTextKey::query()->firstOrNew(['key' => $key]);

        if (!$textKey->exists) {
            $textKey->is_active = (bool) ($attributes['is_active'] ?? true);
        }

        if (array_key_exists('group', $attributes)) {
            $textKey->group = $attributes['group'];
        }

        if (array_key_exists('is_active', $attributes)) {
            $textKey->is_active = (bool) $attributes['is_active'];
        }

        $textKey->save();

        return $textKey;
    }

    public function upsertValue(
        string $key,
        string $languageCode,
        ?string $text,
        ?string $mediaUrl = null,
        ?array $mediaMeta = null,
        ?bool $needsReview = null
    ): UsimTextValue {
        $textKey = $this->createOrUpdateKey($key);
        $language = UsimLanguage::query()->byCode($languageCode)->firstOrFail();

        $payload = [
            'text_value' => $text,
            'media_url' => $mediaUrl,
            'media_meta' => $mediaMeta,
        ];

        if ($needsReview !== null) {
            $payload['needs_review'] = $needsReview;
        }

        return UsimTextValue::query()->updateOrCreate(
            [
                'text_key_id' => $textKey->id,
                'language_id' => $language->id,
            ],
            $payload
        );
    }

    public function deleteKey(string $key): bool
    {
        return (bool) UsimTextKey::query()->byKey($key)->delete();
    }

    public function deleteValue(string $key, string $languageCode): bool
    {
        $textKey = UsimTextKey::query()->byKey($key)->first();
        if (!$textKey) {
            return false;
        }

        $language = UsimLanguage::query()->byCode($languageCode)->first();
        if (!$language) {
            return false;
        }

        return (bool) UsimTextValue::query()
            ->where('text_key_id', $textKey->id)
            ->where('language_id', $language->id)
            ->delete();
    }

    public function listKeys(?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        return UsimTextKey::query()
            ->when($search, function ($query) use ($search) {
                $query->where('key', 'like', '%' . $search . '%')
                    ->orWhere('group', 'like', '%' . $search . '%');
            })
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function ensureLanguageExists(string $code): void
    {
        if (UsimLanguage::query()->byCode($code)->exists()) {
            return;
        }

        $hasFallback = UsimLanguage::query()->where('is_fallback', true)->exists();
        $name = strtoupper($code);
        $this->upsertLanguage($code, $name, $name, true, !$hasFallback);
    }
}
