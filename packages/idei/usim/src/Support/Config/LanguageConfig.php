<?php

namespace Idei\Usim\Support\Config;

final class LanguageConfig
{
    public function __construct(
        public private(set) string $code,
        public private(set) string $name,
        public private(set) string $nativeName,
        public private(set) bool $active,
    ) {}
}
