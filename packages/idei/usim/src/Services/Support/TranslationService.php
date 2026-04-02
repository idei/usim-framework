<?php

namespace Idei\Usim\Services\Support;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Models\UsimTextValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class TranslationService
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
        return UsimTextKey::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $attributes['group'] ?? null,
                'description' => $attributes['description'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]
        );
    }

    public function upsertValue(
        string $key,
        string $languageCode,
        ?string $text,
        ?string $mediaUrl = null,
        ?array $mediaMeta = null
    ): UsimTextValue {
        $textKey = $this->createOrUpdateKey($key);
        $language = UsimLanguage::query()->byCode($languageCode)->firstOrFail();

        return UsimTextValue::query()->updateOrCreate(
            [
                'text_key_id' => $textKey->id,
                'language_id' => $language->id,
            ],
            [
                'text_value' => $text,
                'media_url' => $mediaUrl,
                'media_meta' => $mediaMeta,
            ]
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
                    ->orWhere('group', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            })
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function getValue(string $key, array $params = [], ?string $languageCode = null): string
    {
        $textValue = $this->resolveTextValue($key, $languageCode);

        if ($textValue === null || $textValue === '') {
            return $key;
        }

        return $this->replaceParams($textValue, $params);
    }

    public function getEntry(string $key, ?string $languageCode = null): ?array
    {
        $entry = $this->resolveValueEntry($key, $languageCode);

        if (!$entry) {
            return null;
        }

        return [
            'text' => $entry->text_value,
            'media_url' => $entry->media_url,
            'media_meta' => $entry->media_meta,
            'language_code' => $entry->language?->code,
            'key' => $entry->textKey?->key,
        ];
    }

    protected function resolveTextValue(string $key, ?string $languageCode): ?string
    {
        $entry = $this->resolveValueEntry($key, $languageCode);

        return $entry?->text_value;
    }

    protected function resolveValueEntry(string $key, ?string $languageCode): ?UsimTextValue
    {
        $targetLocale = $this->resolveLocale($languageCode);
        $fallbackLocale = $this->resolveFallbackLocale();

        $candidates = array_values(array_unique([$targetLocale, $fallbackLocale]));

        foreach ($candidates as $candidateLocale) {
            $entry = UsimTextValue::query()
                ->whereHas('textKey', function ($query) use ($key): void {
                    $query->where('key', $key)->where('is_active', true);
                })
                ->whereHas('language', function ($query) use ($candidateLocale): void {
                    $query->where('code', $candidateLocale)->where('is_active', true);
                })
                ->with(['language', 'textKey'])
                ->first();

            if ($entry && $entry->text_value !== null && $entry->text_value !== '') {
                return $entry;
            }
        }

        return null;
    }

    protected function replaceParams(string $value, array $params): string
    {
        if ($params === []) {
            return $value;
        }

        $replacePairs = [];

        foreach ($params as $name => $replacement) {
            $replacePairs[':' . $name] = (string) $replacement;
        }

        return strtr($value, $replacePairs);
    }

    protected function resolveLocale(?string $languageCode): string
    {
        return $languageCode
            ?? app()->getLocale()
            ?? config('ui-services.i18n.default_locale', 'en');
    }

    protected function resolveFallbackLocale(): string
    {
        return config('ui-services.i18n.fallback_locale', 'en');
    }

    public function safeGetValue(string $key, array $params = [], ?string $languageCode = null): ?string
    {
        try {
            return $this->getValue($key, $params, $languageCode);
        } catch (QueryException) {
            return null;
        }
    }
}
