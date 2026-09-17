<?php

namespace Idei\Usim\Support\Config;

final class UserConfig
{
    /**
     * @param array<string, array<int, string>> $unitRoles
     */
    public function __construct(
        public private(set) string $firstName,
        public private(set) string $lastName,
        public private(set) string $email,
        public private(set) string $password,
        public private(set) array $unitRoles = [],
    ) {}
}
