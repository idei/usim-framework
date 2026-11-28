<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class PermissionConfig
{
    /**
     * @param  array<string, array{display_name: string, description: string}>  $defaultTranslations
     */
    public function __construct(
        public private(set) array $defaultTranslations = [],
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        /** @var array<string, array{display_name: string, description: string}> $defaultTranslations */
        $defaultTranslations = $reader->array('default_translations');

        return new self(
            defaultTranslations: $defaultTranslations,
        );
    }
}
