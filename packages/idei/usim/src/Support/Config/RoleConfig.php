<?php

namespace Idei\Usim\Support\Config;

final class RoleConfig
{
    /**
     * @param array<string, array{display_name: string, description: string}> $defaultTranslations
     * @param array<int, string> $permissions
     */
    public function __construct(
        public private(set) array $defaultTranslations = [],
        public private(set) int $priority = 10,
        public private(set) ?string $homeScreen = null,
        public private(set) array $permissions = [],
        public private(set) string $guardName = 'web',
    ) {}
}
