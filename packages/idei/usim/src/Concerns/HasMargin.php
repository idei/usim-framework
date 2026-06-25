<?php
// Concerns/HasMargin.php
namespace Idei\Usim\Concerns;

use Idei\Usim\ValueObjects\Spacing;

trait HasMargin
{
    abstract protected function setConfig(string $key, mixed $value): static;

    public function margin(Spacing $margin): static
    {
        return $this->setConfig('margin', (string) Spacing::from($margin));
    }

    public function marginTop(Spacing $margin): static
    {
        return $this->setConfig('margin_top', (string) Spacing::from($margin));
    }

    public function marginRight(Spacing $margin): static
    {
        return $this->setConfig('margin_right', (string) Spacing::from($margin));
    }

    public function marginBottom(Spacing $margin): static
    {
        return $this->setConfig('margin_bottom', (string) Spacing::from($margin));
    }

    public function marginLeft(Spacing $margin): static
    {
        return $this->setConfig('margin_left', (string) Spacing::from($margin));
    }
}
