<?php

// Contracts/Marginable.php
namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\Spacing;

interface Marginable
{
    public function margin(Spacing $margin): static;
    public function marginTop(Spacing $margin): static;
    public function marginRight(Spacing $margin): static;
    public function marginBottom(Spacing $margin): static;
    public function marginLeft(Spacing $margin): static;
}
