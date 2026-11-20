<?php

namespace Idei\Usim\Navigation\Contracts;

interface MenuTargetInterface
{
    public function clearItems(): self;

    /**
     * @param  array<string, mixed>  $params
     * @param  list<array<string, mixed>>  $submenu
     */
    public function item(
        string $label,
        ?string $action = null,
        array $params = [],
        ?string $icon = null,
        array $submenu = [],
        bool $visible = true
    ): self;

    public function link(string $label, string $url, ?string $icon = null, bool $visible = true): self;

    public function separator(bool $visible = true): self;
}
