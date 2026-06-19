<?php

// Contracts/Sizeable.php
namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\Size;

interface Sizeable
{
    public function width(Size|int $width): static;
    public function getWidth(): Size;

    public function height(Size|int $height): static;
    public function getHeight(): Size;

    public function minWidth(Size|int $width): static;
    public function getMinWidth(): ?Size;

    public function minHeight(Size|int $height): static;
    public function getMinHeight(): ?Size;

    public function maxWidth(Size|int $width): static;
    public function getMaxWidth(): ?Size;

    public function maxHeight(Size|int $height): static;
    public function getMaxHeight(): ?Size;
}
