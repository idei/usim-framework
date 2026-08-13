<?php
// Concerns/HasGap.php
namespace Idei\Usim\Concerns;

use Idei\Usim\ValueObjects\Spacing;

trait HasGap
{
    abstract protected function setConfig(string $key, mixed $value): static;

    public function gap(Spacing $gap): static
    {
        return $this->setConfig('gap', (string) Spacing::from($gap));
    }

    public function rowGap(Spacing $gap): static
    {
        return $this->setConfig('row_gap', (string) Spacing::from($gap));
    }

    public function columnGap(Spacing $gap): static
    {
        return $this->setConfig('column_gap', (string) Spacing::from($gap));
    }
}
