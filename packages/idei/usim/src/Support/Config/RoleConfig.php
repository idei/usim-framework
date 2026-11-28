<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class RoleConfig
{
    /**
     * @param  array<string, array{display_name: string, description: string}>  $defaultTranslations
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public private(set) array $defaultTranslations = [],
        public private(set) int $priority = 10,
        public private(set) ?string $homeScreen = null,
        public private(set) array $permissions = [],
        public private(set) string $guardName = 'web',
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        /** @var array<string, array{display_name: string, description: string}> $defaultTranslations */
        $defaultTranslations = $reader->array('default_translations');

        /** @var array<int, string> $permissions */
        $permissions = $reader->array('permissions');

        return new self(
            defaultTranslations: $defaultTranslations,
            priority: $reader->int('priority', 10),
            homeScreen: $reader->nullableString('home_screen'),
            permissions: $permissions,
            guardName: $reader->string('guard_name', 'web'),
        );
    }
}
