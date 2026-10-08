<?php

namespace Idei\Usim\Navigation;

use Idei\Usim\Components\MenuDropdown;

readonly class TriggerConfig
{
    public function __construct(
        public ?string $label = null,
        public ?string $icon = null,
        public ?string $image = null,
        public ?string $alt = null,
        public string $style = 'default'
    ) {}

    public static function make(?string $label = '☰', ?string $icon = null, string $style = 'default'): self
    {
        return new self(
            label: $label ?? '☰',
            icon: $icon,
            style: $style
        );
    }

    public static function image(string $image, string $alt = 'User', ?string $label = null, string $style = 'default'): self
    {
        return new self(
            label: $label,
            image: $image,
            alt: $alt,
            style: $style
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $label = isset($data['label']) && is_string($data['label']) ? $data['label'] : null;
        $icon = isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null;
        $image = isset($data['image']) && is_string($data['image']) ? $data['image'] : null;
        $alt = isset($data['alt']) && is_string($data['alt']) ? $data['alt'] : null;
        $style = isset($data['style']) && is_string($data['style']) ? $data['style'] : 'default';

        return new self(
            label: $label,
            icon: $icon,
            image: $image,
            alt: $alt,
            style: $style
        );
    }

    public function applyTo(MenuDropdown $dropdown): void
    {
        if ($this->image !== null) {
            $dropdown->triggerImage($this->image, $this->alt ?? ($this->label ?? 'User'), $this->label, $this->style);

            return;
        }

        $dropdown->trigger($this->label ?? '☰', $this->icon, $this->style);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'image' => $this->image,
            'alt' => $this->alt,
            'style' => $this->style,
        ];
    }
}
