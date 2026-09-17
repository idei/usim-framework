<?php
namespace Idei\Usim\Support\Config;

final class DeviceConfig
{
    /**
     * @param array<string, mixed> $specs
     * @param array<string, array<int, string>> $unitRoles
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $specs = [],
        public private(set) array $unitRoles = [],
    ) {}
}
