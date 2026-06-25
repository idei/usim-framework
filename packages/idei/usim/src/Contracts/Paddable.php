<?php

// Contracts/Paddable.php
namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\Spacing;

interface Paddable
{
    public function padding(Spacing $padding): static;
    public function paddingTop(Spacing $padding): static;
    public function paddingRight(Spacing $padding): static;
    public function paddingBottom(Spacing $padding): static;
    public function paddingLeft(Spacing $padding): static;
}
