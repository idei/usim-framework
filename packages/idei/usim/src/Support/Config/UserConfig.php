<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

final class UserConfig
{
    /**
     * @param  array<string, array<int, string>>  $unitRoles
     */
    public function __construct(
        public private(set) string $firstName,
        public private(set) string $lastName,
        public private(set) string $email,
        public private(set) string $password,
        public private(set) array $unitRoles = [],
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $reader = new ConfigReader($data);

        /** @var array<string, array<int, string>> $unitRoles */
        $unitRoles = $reader->array('unit_roles');

        return new self(
            firstName: $reader->string('first_name'),
            lastName: $reader->string('last_name'),
            email: $reader->string('email'),
            password: $reader->string('password'),
            unitRoles: $unitRoles,
        );
    }
}
