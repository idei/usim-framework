<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class DeviceConfig
{
    /**
     * @param  array<string, mixed>  $specs
     * @param  array<string, array<int, string>>  $unitRoles
     */
    public function __construct(
        public private(set) string $name,
        public private(set) array $specs = [],
        public private(set) array $unitRoles = [],
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        /** @var array<string, mixed> $specs */
        $specs = $reader->array('specs');

        /** @var array<string, array<int, string>> $unitRoles */
        $unitRoles = $reader->array('unit_roles');

        return new self(
            name: $reader->string('name'),
            specs: $specs,
            unitRoles: $unitRoles,
        );
    }
}
