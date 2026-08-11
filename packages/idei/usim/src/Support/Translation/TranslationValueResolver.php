<?php

namespace Idei\Usim\Support\Translation;

use Idei\Usim\Models\UsimTextValue;

class TranslationValueResolver
{
    /**
     * @return array{text: string|null, needs_review: bool, media_url: string|null, media_meta: array<string, mixed>|null, language_code: string|null, key: string|null}|null
     */
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

    /**
     * @param array<string, mixed> $params
     */
    public function getValue(string $key, array $params = [], ?string $languageCode = null): string
    {
        $textValue = $this->resolveTextValue($key, $languageCode);

        if ($textValue === null || $textValue === '') {
            return $key;
        }

        return $this->replaceParams($textValue, $params);
    }

    /**
     * @return array{text: string|null, needs_review: bool, media_url: string|null, media_meta: array<string, mixed>|null, language_code: string|null, key: string|null}|null
     */
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

    /**
     * @param array<string, mixed> $params
     */
    private function replaceParams(string $value, array $params): string
    {
        if ($params === []) {
            return $value;
        }

        $replacePairs = [];
        foreach ($params as $name => $replacement) {
            $replacePairs[':' . $name] = $this->stringifyReplacement($replacement);
        }

        return strtr($value, $replacePairs);
    }

    private function stringifyReplacement(mixed $replacement): string
    {
        if (is_string($replacement)) {
            return $replacement;
        }

        if (is_int($replacement) || is_float($replacement) || is_bool($replacement) || $replacement === null) {
            return strval($replacement);
        }

        if (is_object($replacement) && method_exists($replacement, '__toString')) {
            return (string) $replacement;
        }

        if (is_array($replacement)) {
            return 'Array';
        }

        return '';
    }

    private function resolveLocale(?string $languageCode): string
    {
        if ($languageCode !== null && $languageCode !== '') {
            return $languageCode;
        }

        $locale = app()->getLocale();
        if ($locale !== '') {
            return $locale;
        }

        $defaultLocale = config('usim.i18n.default_locale', 'en');

        if (is_string($defaultLocale) && $defaultLocale !== '') {
            return $defaultLocale;
        }

        return 'en';
    }

    private function resolveFallbackLocale(): string
    {
        $fallbackLocale = config('usim.i18n.fallback_locale', 'en');

        return is_string($fallbackLocale) && $fallbackLocale !== '' ? $fallbackLocale : 'en';
    }
}
