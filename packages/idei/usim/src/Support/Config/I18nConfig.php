<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class I18nConfig
{
    /**
     * @param  array<int, LanguageConfig>  $languages
     * @param  array<string, string>  $keyPrefixes
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

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        return new self(
            defaultLocale: $reader->string('default_locale', 'en'),
            fallbackLocale: $reader->string('fallback_locale', 'en'),
            autoKeyMaxLength: $reader->int('auto_key_max_length', 30),
            logChannel: $reader->string('log_channel', 'i18n'),
            logAutokeySuggestions: $reader->bool('log_autokey_suggestions', true),
            languages: ConfigReader::listEntries($reader->array('languages'), LanguageConfig::fromArray(...)),
            keyPrefixes: $reader->stringMap('i18n_key_prefixes'),
        );
    }
}
