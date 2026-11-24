<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Enums\Style;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Presentation DTO representing a single action button/menu entry on a card.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CardActionDTO implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  'solid'|'outline'|'ghost'  $variant
     */
    public function __construct(
        public string $label,
        public ?string $action = null,
        public array $parameters = [],
        public ?string $url = null,
        public string $target = '_self',
        public ?string $icon = null,
        public Style $style = Style::DEFAULT,
        public string $variant = 'outline',
        public bool $disabled = false,
        public bool $overflow = false,
        public ?string $tooltip = null,
    ) {}

    /**
     * Fluent-friendly static constructor accepting Style enums or string values.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function make(
        string $label,
        ?string $action = null,
        array $parameters = [],
        ?string $url = null,
        string $target = '_self',
        ?string $icon = null,
        Style|string $style = Style::DEFAULT,
        string $variant = 'outline',
        bool $disabled = false,
        bool $overflow = false,
        ?string $tooltip = null,
    ): self {
        return new self(
            label: $label,
            action: $action !== null && trim($action) !== '' ? trim($action) : null,
            parameters: $parameters,
            url: $url !== null && trim($url) !== '' ? trim($url) : null,
            target: trim($target) !== '' ? trim($target) : '_self',
            icon: $icon !== null && trim($icon) !== '' ? trim($icon) : null,
            style: self::normalizeStyle($style),
            variant: self::normalizeVariant($variant),
            disabled: $disabled,
            overflow: $overflow,
            tooltip: $tooltip !== null && trim($tooltip) !== '' ? trim($tooltip) : null,
        );
    }

    /**
     * Hydrate a CardActionDTO from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawLabel = $data['label'] ?? '';
        $label = is_scalar($rawLabel) ? trim((string) $rawLabel) : '';

        $rawAction = $data['action'] ?? null;
        $action = is_string($rawAction) && trim($rawAction) !== '' ? trim($rawAction) : null;

        /** @var array<string, mixed> $parameters */
        $parameters = isset($data['parameters']) && is_array($data['parameters']) ? $data['parameters'] : [];

        $rawUrl = $data['url'] ?? null;
        $url = is_string($rawUrl) && trim($rawUrl) !== '' ? trim($rawUrl) : null;

        $rawTarget = $data['target'] ?? '_self';
        $target = is_string($rawTarget) && trim($rawTarget) !== '' ? trim($rawTarget) : '_self';

        $rawIcon = $data['icon'] ?? null;
        $icon = is_scalar($rawIcon) && trim((string) $rawIcon) !== '' ? trim((string) $rawIcon) : null;

        $rawStyle = $data['style'] ?? Style::DEFAULT;
        $style = ($rawStyle instanceof Style || is_string($rawStyle))
            ? self::normalizeStyle($rawStyle)
            : Style::DEFAULT;

        $rawVariant = $data['variant'] ?? 'outline';
        $variant = is_string($rawVariant) ? self::normalizeVariant($rawVariant) : 'outline';

        $rawTooltip = $data['tooltip'] ?? null;
        $tooltip = is_scalar($rawTooltip) && trim((string) $rawTooltip) !== '' ? trim((string) $rawTooltip) : null;

        return new self(
            label: $label,
            action: $action,
            parameters: $parameters,
            url: $url,
            target: $target,
            icon: $icon,
            style: $style,
            variant: $variant,
            disabled: (bool) ($data['disabled'] ?? false),
            overflow: (bool) ($data['overflow'] ?? false),
            tooltip: $tooltip,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'label' => $this->label,
            'style' => $this->style->value,
            'variant' => $this->variant,
            'disabled' => $this->disabled,
            'overflow' => $this->overflow,
        ];

        if ($this->action !== null) {
            $payload['action'] = $this->action;
        }

        if ($this->parameters !== []) {
            $payload['parameters'] = $this->parameters;
        }

        if ($this->url !== null) {
            $payload['url'] = $this->url;
            $payload['target'] = $this->target;
        }

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon;
        }

        if ($this->tooltip !== null) {
            $payload['tooltip'] = $this->tooltip;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function normalizeStyle(Style|string $style): Style
    {
        if ($style instanceof Style) {
            return $style;
        }

        return Style::tryFrom(strtolower(trim($style))) ?? Style::DEFAULT;
    }

    /**
     * @return 'solid'|'outline'|'ghost'
     */
    private static function normalizeVariant(string $variant): string
    {
        return match (strtolower(trim($variant))) {
            'solid' => 'solid',
            'ghost' => 'ghost',
            default => 'outline',
        };
    }
}
