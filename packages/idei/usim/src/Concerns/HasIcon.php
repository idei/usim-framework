<?php

namespace Idei\Usim\Concerns;

use BackedEnum;
use Idei\Usim\Enums\Align;

trait HasIcon
{
    abstract protected function setConfig(string $key, mixed $value): static;

    /**
     * Set the component icon by semantic name, emoji, or image source.
     *
     * @param  string|BackedEnum|null  $icon  Icon name, emoji, or SVG/PNG source
     * @param  Align|string|null  $position  Optional icon placement (left, right, top, bottom)
     * @return static For method chaining
     */
    public function icon(string|BackedEnum|null $icon, Align|string|null $position = null): static
    {
        $value = $icon instanceof BackedEnum ? (string) $icon->value : $icon;
        $this->setConfig('icon', $value);

        if ($position !== null) {
            $this->iconPosition($position);
        }

        return $this;
    }

    /**
     * Set the icon position relative to the component text.
     *
     * @param  Align|string  $position  The position (left, right, top, bottom)
     * @return static For method chaining
     */
    public function iconPosition(Align|string $position): static
    {
        $pos = $position instanceof Align ? $position->value : $position;

        return $this->setConfig('icon_position', $pos);
    }
}
