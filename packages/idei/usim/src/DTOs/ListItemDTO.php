<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Enums\Style;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Presentation DTO representing a single visual row in an ItemList component.
 *
 * Intentionally excludes selection state (`selected`), which lives on the ItemList
 * component (`selected_items`) as the single source of truth across searches.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ListItemDTO implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int|string $id,
        public string $label,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public ?string $badge = null,
        public Style $badgeStyle = Style::DEFAULT,
        public Style $style = Style::DEFAULT,
        public bool $disabled = false,
        public bool $editable = true,
        public bool $deletable = true,
        public array $meta = [],
    ) {}

    /**
     * Fluent-friendly static constructor accepting Style enums or string values.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        int|string $id,
        string $label,
        ?string $subtitle = null,
        ?string $icon = null,
        ?string $badge = null,
        Style|string $badgeStyle = Style::DEFAULT,
        Style|string $style = Style::DEFAULT,
        bool $disabled = false,
        bool $editable = true,
        bool $deletable = true,
        array $meta = [],
    ): self {
        return new self(
            id: $id,
            label: $label,
            subtitle: $subtitle,
            icon: $icon,
            badge: $badge,
            badgeStyle: self::normalizeStyle($badgeStyle),
            style: self::normalizeStyle($style),
            disabled: $disabled,
            editable: $editable,
            deletable: $deletable,
            meta: $meta,
        );
    }

    /**
     * Hydrate a ListItemDTO from a normalized or loose associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int|string $fallbackId = 0): self
    {
        $rawId = $data['id'] ?? $data['value'] ?? $data['_model_id'] ?? $fallbackId;
        $id = is_int($rawId) || is_string($rawId) ? $rawId : (string) $fallbackId;

        $rawLabel = $data['label'] ?? $data['title'] ?? $data['name'] ?? (string) $id;
        $label = is_scalar($rawLabel) ? (string) $rawLabel : (string) $id;

        $rawSubtitle = $data['subtitle'] ?? $data['description'] ?? null;
        $subtitle = is_scalar($rawSubtitle) && (string) $rawSubtitle !== '' ? (string) $rawSubtitle : null;

        $rawIcon = $data['icon'] ?? null;
        $icon = is_scalar($rawIcon) && (string) $rawIcon !== '' ? (string) $rawIcon : null;

        $rawBadge = $data['badge'] ?? null;
        $badge = is_scalar($rawBadge) && (string) $rawBadge !== '' ? (string) $rawBadge : null;

        $rawBadgeStyle = $data['badge_style'] ?? $data['badgeStyle'] ?? Style::DEFAULT;
        $badgeStyle = ($rawBadgeStyle instanceof Style || is_string($rawBadgeStyle))
            ? self::normalizeStyle($rawBadgeStyle)
            : Style::DEFAULT;

        $rawStyle = $data['style'] ?? Style::DEFAULT;
        $style = ($rawStyle instanceof Style || is_string($rawStyle))
            ? self::normalizeStyle($rawStyle)
            : Style::DEFAULT;

        /** @var array<string, mixed> $meta */
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];

        return new self(
            id: $id,
            label: $label,
            subtitle: $subtitle,
            icon: $icon,
            badge: $badge,
            badgeStyle: $badgeStyle,
            style: $style,
            disabled: (bool) ($data['disabled'] ?? false),
            editable: array_key_exists('editable', $data) ? (bool) $data['editable'] : true,
            deletable: array_key_exists('deletable', $data) ? (bool) $data['deletable'] : true,
            meta: $meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'label' => $this->label,
            'style' => $this->style->value,
            'badge_style' => $this->badgeStyle->value,
            'disabled' => $this->disabled,
            'editable' => $this->editable,
            'deletable' => $this->deletable,
        ];

        if ($this->subtitle !== null && $this->subtitle !== '') {
            $payload['subtitle'] = $this->subtitle;
        }

        if ($this->icon !== null && $this->icon !== '') {
            $payload['icon'] = $this->icon;
        }

        if ($this->badge !== null && $this->badge !== '') {
            $payload['badge'] = $this->badge;
        }

        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
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
}
