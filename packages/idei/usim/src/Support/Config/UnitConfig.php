<?php

namespace Idei\Usim\Support\Config;

final class UnitConfig
{
    /**
     * @param array<string, array{display_name: string, description: string}> $defaultTranslations
     */
    public function __construct(
        public private(set) ?string $parent = null,
        public private(set) ?string $type = null,
        public private(set) array $defaultTranslations = [],
    ) {}
}
