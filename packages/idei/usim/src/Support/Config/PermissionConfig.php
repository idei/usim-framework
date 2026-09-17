<?php

namespace Idei\Usim\Support\Config;

final class PermissionConfig
{
    /**
     * @param array<string, array{display_name: string, description: string}> $defaultTranslations
     */
    public function __construct(
        public private(set) array $defaultTranslations = [],
    ) {}
}
