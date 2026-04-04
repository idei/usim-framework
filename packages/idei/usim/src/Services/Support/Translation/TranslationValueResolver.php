<?php

namespace Idei\Usim\Services\Support\Translation;

use Idei\Usim\Models\UsimTextValue;

class TranslationValueResolver
{
    public function getDirectEntry(string $key, string $languageCode): ?array
    {
        $entry = UsimTextValue::query()
            ->whereHas('textKey', function ($query) use ($key): void {
                $query->where('key', $key)->where('is_active', true);
            })
            ->whereHas('language', function ($query) use ($languageCode): void {
                $query->where('code', $languageCode)->where('is_active', true);
            })
            ->with(['language', 'textKey'])
            ->first();

        if (!$entry) {
            return null;
        }

        return [
            'text' => $entry->text_value,
            'needs_review' => (bool) $entry->needs_review,
            'media_url' => $entry->media_url,
            'media_meta' => $entry->media_meta,
            'language_code' => $entry->language?->code,
            'key' => $entry->textKey?->key,
        ];
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
            'needs_review' => (bool) $entry->needs_review,
            'media_url' => $entry->media_url,
            'media_meta' => $entry->media_meta,
            'language_code' => $entry->language?->code,
            'key' => $entry->textKey?->key,
        ];
    }

    private function resolveTextValue(string $key, ?string $languageCode): ?string
    {
        $entry = $this->resolveValueEntry($key, $languageCode);

        return $entry?->text_value;
    }

    private function resolveValueEntry(string $key, ?string $languageCode): ?UsimTextValue
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

    private function replaceParams(string $value, array $params): string
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

    private function resolveLocale(?string $languageCode): string
    {
        return $languageCode
            ?? app()->getLocale()
            ?? config('ui-services.i18n.default_locale', 'en');
    }

    private function resolveFallbackLocale(): string
    {
        return config('ui-services.i18n.fallback_locale', 'en');
    }
}
