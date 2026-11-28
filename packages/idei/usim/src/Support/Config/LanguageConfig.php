<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class LanguageConfig
{
    public function __construct(
        public private(set) string $code,
        public private(set) string $name,
        public private(set) string $nativeName,
        public private(set) bool $active,
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        return new self(
            code: $reader->string('code'),
            name: $reader->string('name'),
            nativeName: $reader->string('native_name'),
            active: $reader->bool('active', false),
        );
    }
}
