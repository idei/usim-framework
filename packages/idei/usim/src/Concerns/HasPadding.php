<?php

// Concerns/HasPadding.php
namespace Idei\Usim\Concerns;

use Idei\Usim\ValueObjects\Spacing;

trait HasPadding
{
    abstract protected function setConfig(string $key, mixed $value): static;

    public function padding(Spacing $padding): static
    {
        return $this->setConfig('padding', (string) Spacing::from($padding));
    }

    public function paddingTop(Spacing $padding): static
    {
        return $this->setConfig('padding_top', (string) Spacing::from($padding));
    }

    public function paddingRight(Spacing $padding): static
    {
        return $this->setConfig('padding_right', (string) Spacing::from($padding));
    }

    public function paddingBottom(Spacing $padding): static
    {
        return $this->setConfig('padding_bottom', (string) Spacing::from($padding));
    }

    public function paddingLeft(Spacing $padding): static
    {
        return $this->setConfig('padding_left', (string) Spacing::from($padding));
    }

    // paddingEach desaparece — se reemplaza por padding(Spacing::each(...))
}
