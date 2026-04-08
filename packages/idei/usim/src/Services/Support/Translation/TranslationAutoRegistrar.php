<?php

namespace Idei\Usim\Services\Support\Translation;

use Idei\Usim\Models\UsimTextKey;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        $normalizedInput = $this->normalizeHumanText($input);
        $generatedKey = $this->generateAutoKeyFromText($normalizedInput, $group);

        // If the generated key already belongs to this group, keep it as-is
        // and avoid re-inserting/updating fallback translation data.
        $alreadyExistsForGroup = $this->keyExistsForGroup($generatedKey, $group);
        if ($alreadyExistsForGroup) {
            $this->logI18nSuggestion($normalizedInput, $generatedKey, $callerContext, false);
            return $generatedKey;
        }

        $this->storeFallbackTranslation($generatedKey, $normalizedInput, true, $callerContext['group']);
        $this->logI18nSuggestion($normalizedInput, $generatedKey, $callerContext, true);

        return $generatedKey;
    }

    public function deriveKeyCandidate(string $input): string
    {
        if ($this->isSlug($input)) {
            return $input;
        }

        $callerContext = $this->contextResolver->resolveCallerContext();
        $normalizedInput = $this->normalizeHumanText($input);

        return $this->generateAutoKeyFromText($normalizedInput, $callerContext['group']);
    }

    private function findGroupVariantKey(string $base, ?string $group): ?string
    {
        $query = UsimTextKey::query()
            ->where(function ($q) use ($base): void {
                $q->where('key', $base)
                    ->orWhere('key', 'like', $base . '_%');
            })
            ->orderBy('key');

        if ($group === null || $group === '') {
            $query->whereNull('group');
        } else {
            $query->where('group', $group);
        }

        $match = $query->first();

        return $match?->key;
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
        return preg_match('/^(?:[a-z0-9]{2,})(?:[._][a-z0-9]{2,})*$/', $value) === 1;
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

        // Reuse any already generated variant for this group to keep key derivation stable.
        $groupVariant = $this->findGroupVariantKey($base, $group);
        if ($groupVariant) {
            return $groupVariant;
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $suffix = substr(md5($group . '|' . $text . '|' . $attempt), 0, 6);
            $candidate = $base . '_' . $suffix;

            if (!UsimTextKey::query()->byKey($candidate)->exists() || $this->keyExistsForGroup($candidate, $group)) {
                return $candidate;
            }
        }

        return $base . '_' . (string) time();
    }

    private function buildTextSlugBase(string $text): string
    {
        // Convert line breaks to spaces for slug generation while keeping fallback text normalized.
        $slugSource = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $slug = Str::slug($slugSource, '_');

        if ($slug === '') {
            $slug = 'text_key';
        }

        $maxLength = $this->resolveAutoKeyMaxLength();
        if (strlen($slug) <= $maxLength) {
            return $slug;
        }

        // Try to keep full words by extending to the next separator after maxLength.
        // Example (max=20): para_poder_registrarse_debe... -> para_poder_registrarse
        $nextSeparatorPos = strpos($slug, '_', $maxLength);

        if ($nextSeparatorPos !== false) {
            $expanded = substr($slug, 0, $nextSeparatorPos);
            $expanded = trim($expanded, '._');

            if ($expanded !== '') {
                return $expanded;
            }
        }

        // If there is no separator after maxLength, we are inside the last token.
        // Keep the full final word instead of truncating it mid-word.
        return $slug;
    }

    private function resolveAutoKeyMaxLength(): int
    {
        $configured = (int) config('ui-services.i18n.auto_key_max_length', 20);

        if ($configured < 3) {
            return 3;
        }

        return $configured;
    }

    private function normalizeHumanText(string $text): string
    {
        // Convert escaped newlines ("\\n") and platform line endings into canonical LF.
        $normalized = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);

        return $normalized;
    }

    /**
     * @param array{group?: string, file?: string|null, line?: int|null} $callerContext
     */
    private function logI18nSuggestion(string $originalText, string $generatedKey, array $callerContext, bool $newlyCreated): void
    {
        if (!config('ui-services.i18n.log_autokey_suggestions', true)) {
            return;
        }

        $file = (string) ($callerContext['file'] ?? 'unknown');
        $line = isset($callerContext['line']) ? (int) $callerContext['line'] : null;
        $column = $this->resolveSourceColumn($file, $line, $originalText);
        $group = (string) ($callerContext['group'] ?? 'global');

        $message = $newlyCreated
            ? 'i18n autokey generated from human text. Replace the literal text with the generated key when possible.'
            : 'i18n autokey reused for human text. Replace the literal text with the generated key when possible.';

        $context = [
            'generated_key' => $generatedKey,
            'source_text' => $originalText,
            'group' => $group,
            'file' => $file,
            'line' => $line,
            'character' => $column,
            'hint' => "Use t('{$generatedKey}') instead of inline human text.",
        ];

        $channel = (string) config('ui-services.i18n.log_channel', 'i18n');

        try {
            Log::channel($channel)->warning($message, $context);
        } catch (\Throwable) {
            Log::warning($message, $context);
        }
    }

    private function resolveSourceColumn(string $file, ?int $line, string $sourceText): ?int
    {
        if ($file === '' || $file === 'unknown' || $line === null || $line < 1 || !is_file($file)) {
            return null;
        }

        $lines = @file($file);
        if (!is_array($lines) || !isset($lines[$line - 1])) {
            return null;
        }

        $lineText = (string) $lines[$line - 1];
        if ($lineText === '') {
            return null;
        }

        $candidateNeedles = array_values(array_unique(array_filter([
            $sourceText,
            str_replace("\n", "\\n", $sourceText),
            addslashes($sourceText),
            addslashes(str_replace("\n", "\\n", $sourceText)),
        ], static fn ($v) => is_string($v) && $v !== '')));

        foreach ($candidateNeedles as $needle) {
            $pos = strpos($lineText, $needle);
            if ($pos !== false) {
                return $pos + 1;
            }
        }

        return null;
    }
}
