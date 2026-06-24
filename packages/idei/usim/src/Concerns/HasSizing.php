<?php

// Concerns/HasSizing.php
namespace Idei\Usim\Concerns;

use Idei\Usim\ValueObjects\Size;

trait HasSizing
{

    // Explicit contract for setConfig to ensure it's implemented in the using class
    abstract protected function setConfig(string $key, mixed $value): static;

    public function width(Size $width): static
    {
        return $this->setConfig('width', (string) Size::from($width));
    }

    public function getWidth(): Size
    {
        return Size::from($this->config['width'] ?? Size::auto());
    }

    public function height(Size $height): static
    {
        return $this->setConfig('height', (string) Size::from($height));
    }

    public function getHeight(): Size
    {
        return Size::from($this->config['height'] ?? Size::auto());
    }

    public function minWidth(Size $width): static
    {
        return $this->setConfig('min_width', (string) Size::from($width));
    }

    public function getMinWidth(): ?Size
    {
        $v = $this->config['min_width'] ?? null;
        return $v !== null ? Size::from($v) : null;
    }

    public function minHeight(Size $height): static
    {
        return $this->setConfig('min_height', (string) Size::from($height));
    }

    public function getMinHeight(): ?Size
    {
        $v = $this->config['min_height'] ?? null;
        return $v !== null ? Size::from($v) : null;
    }

    public function maxWidth(Size $width): static
    {
        return $this->setConfig('max_width', (string) Size::from($width));
    }

    public function getMaxWidth(): ?Size
    {
        $v = $this->config['max_width'] ?? null;
        return $v !== null ? Size::from($v) : null;
    }

    public function maxHeight(Size $height): static
    {
        return $this->setConfig('max_height', (string) Size::from($height));
    }

    public function getMaxHeight(): ?Size
    {
        $v = $this->config['max_height'] ?? null;
        return $v !== null ? Size::from($v) : null;
    }
}
