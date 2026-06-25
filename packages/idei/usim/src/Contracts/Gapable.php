<?php

// Contracts/Gapable.php
namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\Spacing;

interface Gapable
{
    public function gap(Spacing $gap): static;
    public function rowGap(Spacing $gap): static;
    public function columnGap(Spacing $gap): static;
}
