<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Support\Translation\TranslationResolver;
use Illuminate\Contracts\Translation\Translator as LaravelTranslator;
use Illuminate\Translation\Translator;
use Throwable;

class UsimTranslator implements UsimTranslatorInterface
{
    public function __construct(
        protected ?LaravelTranslator $translator = null,
        protected ?TranslationService $translationService = null,
    ) {}

    protected function getTranslator(): LaravelTranslator
    {
        if ($this->translator === null) {
            /** @var LaravelTranslator $translator */
            $translator = app('translator');
            $this->translator = $translator;
        }

        return $this->translator;
    }

    protected function getTranslationService(): ?TranslationService
    {
        if ($this->translationService === null && app()->bound(TranslationService::class)) {
            try {
                /** @var TranslationService $service */
                $service = app(TranslationService::class);
                $this->translationService = $service;
            } catch (Throwable) {
                return null;
            }
        }

        return $this->translationService;
    }

    /**
     * Resolve translated string for the given key, replacing placeholders.
     *
     * @param  array<string, mixed>  $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        return $this->resolveFromLaravel($key, $replace, $locale)
            ?? $this->resolveFromDatabase($key, $replace, $locale)
            ?? $key;
    }

    /**
     * Determine if a translation exists for the given key.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        return $this->hasInLaravel($key, $locale)
            || $this->hasInDatabase($key, $locale);
    }

    /**
     * Get the current active locale.
     */
    public function getLocale(): string
    {
        return $this->getTranslator()->getLocale();
    }

    /**
     * Set the current active locale.
     */
    public function setLocale(string $locale): void
    {
        $this->getTranslator()->setLocale($locale);

        try {
            app()->setLocale($locale);
        } catch (Throwable) {
            // Container or app not available in isolated unit environments
        }
    }

    /**
     * Attempt to resolve the translation through Laravel's translator.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function resolveFromLaravel(string $key, array $replace, ?string $locale): ?string
    {
        try {
            $translator = $this->getTranslator();
            $normalizedParams = $this->normalizeParams($replace);

            foreach (TranslationResolver::buildCandidates($key) as $candidate) {
                $hasTranslation = false;
                if ($translator instanceof Translator) {
                    $hasTranslation = $locale !== null
                        ? $translator->has($candidate, $locale)
                        : $translator->has($candidate);
                } elseif (method_exists($translator, 'has')) {
                    /** @var callable $hasCallback */
                    $hasCallback = [$translator, 'has'];
                    $hasTranslation = (bool) ($locale !== null ? $hasCallback($candidate, $locale) : $hasCallback($candidate));
                } else {
                    $hasTranslation = $translator->get($candidate, [], $locale) !== $candidate;
                }

                if ($hasTranslation) {
                    $result = $translator->get($candidate, $normalizedParams, $locale);
                    if (is_string($result)) {
                        return $result;
                    }
                }
            }
        } catch (Throwable) {
            // Translator unavailable — continue to next resolver.
        }

        return null;
    }

    /**
     * Attempt to resolve the translation through the database TranslationService.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function resolveFromDatabase(string $key, array $replace, ?string $locale): ?string
    {
        try {
            $service = $this->getTranslationService();
            if ($service === null) {
                return null;
            }

            $value = $service->safeGetValue($key, $replace, $locale);

            if ($value !== null && $value !== '' && $value !== $key) {
                return $value;
            }

            $service->registerMissingKey($key);
        } catch (Throwable) {
            // DB unavailable — continue to fallback.
        }

        return null;
    }

    /**
     * Determine if the key exists in Laravel's translator.
     */
    protected function hasInLaravel(string $key, ?string $locale): bool
    {
        try {
            $translator = $this->getTranslator();

            foreach (TranslationResolver::buildCandidates($key) as $candidate) {
                if ($translator instanceof Translator) {
                    $exists = $locale !== null
                        ? $translator->has($candidate, $locale)
                        : $translator->has($candidate);
                } elseif (method_exists($translator, 'has')) {
                    /** @var callable $hasCallback */
                    $hasCallback = [$translator, 'has'];
                    $exists = (bool) ($locale !== null ? $hasCallback($candidate, $locale) : $hasCallback($candidate));
                } else {
                    $exists = $translator->get($candidate, [], $locale) !== $candidate;
                }

                if ($exists) {
                    return true;
                }
            }
        } catch (Throwable) {
            // Translator unavailable
        }

        return false;
    }

    /**
     * Determine if the key exists in the database TranslationService.
     */
    protected function hasInDatabase(string $key, ?string $locale): bool
    {
        try {
            $service = $this->getTranslationService();
            if ($service === null) {
                return false;
            }

            $entry = $service->getEntry($key, $locale);

            return $entry !== null && ($entry['text'] ?? null) !== null && $entry['text'] !== '';
        } catch (Throwable) {
            // DB unavailable
        }

        return false;
    }

    /**
     * Normalize parameter values to scalars/strings expected by Laravel translator.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, bool|float|int|string|null>
     */
    public function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $paramKey => $value) {
            $normalized[$paramKey] = match (true) {
                is_scalar($value) || $value === null => $value,
                is_array($value) => json_encode($value) ?: '',
                is_object($value) && method_exists($value, '__toString') => (string) $value,
                is_resource($value) => get_resource_type($value),
                default => get_debug_type($value),
            };
        }

        return $normalized;
    }
}
