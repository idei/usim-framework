<?php

// Contracts/Sizeable.php
namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\Size;

interface Sizeable
{
    public function width(Size $width): static;
    public function getWidth(): Size;

    public function height(Size $height): static;
    public function getHeight(): Size;

    public function minWidth(Size $width): static;
    public function getMinWidth(): ?Size;

    public function minHeight(Size $height): static;
    public function getMinHeight(): ?Size;

    public function maxWidth(Size $width): static;
    public function getMaxWidth(): ?Size;

    public function maxHeight(Size $height): static;
    public function getMaxHeight(): ?Size;
}
