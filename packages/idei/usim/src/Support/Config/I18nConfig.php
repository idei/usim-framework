<?php

namespace Idei\Usim\Support\Config;

final class I18nConfig
{
    /**
     * @param array<int, LanguageConfig> $languages
     * @param array<string, string> $keyPrefixes
     */
    public function __construct(
        public private(set) string $defaultLocale,
        public private(set) string $fallbackLocale,
        public private(set) int $autoKeyMaxLength,
        public private(set) string $logChannel,
        public private(set) bool $logAutokeySuggestions,
        public private(set) array $languages = [],
        public private(set) array $keyPrefixes = [],
    ) {}
}
