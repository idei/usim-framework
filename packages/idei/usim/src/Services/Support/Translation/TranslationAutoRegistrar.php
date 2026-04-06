<?php

namespace Idei\Usim\Services\Support\Translation;

use Idei\Usim\Models\UsimTextKey;
use Illuminate\Support\Str;

class TranslationAutoRegistrar
{
    public function __construct(
        private readonly TranslationKeyManager $keyManager,
        private readonly TranslationContextResolver $contextResolver
    ) {
    }

    public function resolveOrRegisterKey(string $input): string
    {
        if ($this->isSlug($input)) {
            if (!UsimTextKey::query()->byKey($input)->exists()) {
                $callerContext = $this->contextResolver->resolveCallerContext();
                $this->storeFallbackTranslation($input, $this->slugToFallbackText($input), true, $callerContext['group']);
            }

            return $input;
        }

        $callerContext = $this->contextResolver->resolveCallerContext();
        $group = $callerContext['group'];
        $generatedKey = $this->generateAutoKeyFromText($input, $group);

        // If the generated key already belongs to this group, keep it as-is
        // and avoid re-inserting/updating fallback translation data.
        if ($this->keyExistsForGroup($generatedKey, $group)) {
            return $generatedKey;
        }

        $this->storeFallbackTranslation($generatedKey, $input, true, $callerContext['group']);

        return $generatedKey;
    }

    public function deriveKeyCandidate(string $input): string
    {
        if ($this->isSlug($input)) {
            return $input;
        }

        $callerContext = $this->contextResolver->resolveCallerContext();

        return $this->generateAutoKeyFromText($input, $callerContext['group']);
    }

    private function keyExistsForGroup(string $key, ?string $group): bool
    {
        $query = UsimTextKey::query()->byKey($key);

        if ($group === null || $group === '') {
            $query->whereNull('group');
        } else {
            $query->where('group', $group);
        }

        return $query->exists();
    }

    private function isSlug(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return preg_match('/^(?:[a-z0-9]{3,})(?:[._][a-z0-9]{3,})*$/', $value) === 1;
    }

    private function storeFallbackTranslation(
        string $key,
        string $fallbackText,
        ?bool $needsReview = null,
        ?string $group = null
    ): void {
        if ($group !== null) {
            $attributes = [];

            if ($group !== null) {
                $attributes['group'] = $group;
            }

            $this->keyManager->createOrUpdateKey($key, $attributes);
        }

        $fallbackLocale = $this->resolveFallbackLocale();
        $this->keyManager->ensureLanguageExists($fallbackLocale);
        $this->keyManager->upsertValue(
            $key,
            $fallbackLocale,
            $fallbackText,
            needsReview: $needsReview
        );
    }

    private function resolveFallbackLocale(): string
    {
        return config('ui-services.i18n.fallback_locale', 'en');
    }

    private function slugToFallbackText(string $slug): string
    {
        $sentence = preg_replace('/[._]+/', ' ', strtolower($slug)) ?? $slug;

        return ucfirst(trim($sentence));
    }

    private function generateAutoKeyFromText(string $text, string $group): string
    {
        $base = $this->buildTextSlugBase($text);

        $existing = UsimTextKey::query()->byKey($base)->first();
        if (!$existing) {
            return $base;
        }

        if ($existing->group === $group) {
            return $base;
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = $base . '_' . random_int(1000, 9999);

            if (!UsimTextKey::query()->byKey($candidate)->exists()) {
                return $candidate;
            }
        }

        return $base . '_' . (string) time();
    }

    private function buildTextSlugBase(string $text): string
    {
        $slug = Str::slug($text, '_');

        if ($slug === '') {
            $slug = 'text_key';
        }

        return Str::limit($slug, 20, '');
    }
}
