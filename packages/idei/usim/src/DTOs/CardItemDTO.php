<?php

namespace Idei\Usim\DTOs;

use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\Style;
use Idei\Usim\Support\ImageSourceResolver;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Presentation DTO representing a standardized card item in a CardGrid component.
 *
 * Designed with closed, typed visual slots so cards keep consistent hierarchy,
 * alignment, and Light/Dark theme compatibility while allowing rich per-item
 * media, icons, badges, metrics, attributes, tags, semantic styles, and actions.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CardItemDTO implements Arrayable, JsonSerializable
{
    /**
     * @param  'cover'|'contain'|'scale-down'  $imageFit
     * @param  list<array{label: string, value: string, icon?: string}>  $attributes
     * @param  list<string>  $tags
     * @param  list<CardActionDTO>  $actions
     * @param  array<string, mixed>  $clickParameters
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int|string $id,
        public string $title,
        public ?string $subtitle = null,
        public ?string $eyebrow = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public string $imageFit = 'cover',
        public ?string $aspectRatio = null,
        public ?string $icon = null,
        public ?string $avatar = null,
        public ?string $badge = null,
        public Style $badgeStyle = Style::DEFAULT,
        public ?string $metric = null,
        public ?string $metricLabel = null,
        public Style $metricStyle = Style::DEFAULT,
        public array $attributes = [],
        public array $tags = [],
        public ?string $footerText = null,
        public array $actions = [],
        public ?string $clickAction = null,
        public array $clickParameters = [],
        public ?string $url = null,
        public string $target = '_self',
        public Style $style = Style::DEFAULT,
        public bool $featured = false,
        public bool $disabled = false,
        public ?string $accentColor = null,
        public array $meta = [],
        public ?string $imageStorage = null,
        public ?bool $imagePlaceholder = null,
        public ?string $imagePlaceholderAspect = null,
        public ?string $imagePlaceholderId = null,
        public ?string $imagePlaceholderBackground = null,
        public ?string $imagePlaceholderText = null,
    ) {}

    /**
     * Fluent-friendly static constructor accepting Style enums or string values
     * and CardActionDTO or associative action arrays.
     *
     * @param  array<int, array<string, mixed>>  $attributes
     * @param  array<int, mixed>  $tags
     * @param  array<int, CardActionDTO|array<string, mixed>>  $actions
     * @param  array<string, mixed>  $clickParameters
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        int|string $id,
        string $title,
        ?string $subtitle = null,
        ?string $eyebrow = null,
        ?string $description = null,
        ?string $image = null,
        ?string $imageAlt = null,
        string $imageFit = 'cover',
        ?string $aspectRatio = null,
        ?string $icon = null,
        ?string $avatar = null,
        ?string $badge = null,
        Style|string $badgeStyle = Style::DEFAULT,
        ?string $metric = null,
        ?string $metricLabel = null,
        Style|string $metricStyle = Style::DEFAULT,
        array $attributes = [],
        array $tags = [],
        ?string $footerText = null,
        array $actions = [],
        ?string $clickAction = null,
        array $clickParameters = [],
        ?string $url = null,
        string $target = '_self',
        Style|string $style = Style::DEFAULT,
        bool $featured = false,
        bool $disabled = false,
        ?string $accentColor = null,
        array $meta = [],
        ?string $imageStorage = null,
        ?bool $imagePlaceholder = null,
        AspectRatio|string|null $imagePlaceholderAspect = null,
        int|string|null $imagePlaceholderId = null,
        ?string $imagePlaceholderBackground = null,
        ?string $imagePlaceholderText = null,
    ): self {
        return new self(
            id: $id,
            title: $title,
            subtitle: self::nullableString($subtitle),
            eyebrow: self::nullableString($eyebrow),
            description: self::nullableString($description),
            image: self::nullableString($image),
            imageAlt: self::nullableString($imageAlt),
            imageFit: self::normalizeImageFit($imageFit),
            aspectRatio: self::nullableString($aspectRatio),
            icon: self::nullableString($icon),
            avatar: self::nullableString($avatar),
            badge: self::nullableString($badge),
            badgeStyle: self::normalizeStyle($badgeStyle),
            metric: self::nullableString($metric),
            metricLabel: self::nullableString($metricLabel),
            metricStyle: self::normalizeStyle($metricStyle),
            attributes: self::normalizeAttributes($attributes),
            tags: self::normalizeTags($tags),
            footerText: self::nullableString($footerText),
            actions: self::normalizeActions($actions),
            clickAction: self::nullableString($clickAction),
            clickParameters: $clickParameters,
            url: self::nullableString($url),
            target: trim($target) !== '' ? trim($target) : '_self',
            style: self::normalizeStyle($style),
            featured: $featured,
            disabled: $disabled,
            accentColor: self::nullableString($accentColor),
            meta: $meta,
            imageStorage: ImageSourceResolver::normalizeStorageDirectory(self::nullableString($imageStorage)),
            imagePlaceholder: $imagePlaceholder,
            imagePlaceholderAspect: ImageSourceResolver::normalizeAspectRatio($imagePlaceholderAspect),
            imagePlaceholderId: $imagePlaceholderId !== null ? self::nullableString((string) $imagePlaceholderId) : null,
            imagePlaceholderBackground: self::nullableString($imagePlaceholderBackground),
            imagePlaceholderText: self::nullableString($imagePlaceholderText),
        );
    }

    /**
     * Convert a ListItemDTO into a CardItemDTO for seamless interoperability with Listable services.
     */
    public static function fromListItemDTO(ListItemDTO $item): self
    {
        return new self(
            id: $item->id,
            title: $item->label,
            subtitle: $item->subtitle,
            icon: $item->icon,
            badge: $item->badge,
            badgeStyle: $item->badgeStyle,
            style: $item->style,
            disabled: $item->disabled,
            meta: $item->meta,
        );
    }

    /**
     * Hydrate a CardItemDTO from a normalized or loose associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int|string $fallbackId = 0): self
    {
        $rawId = $data['id'] ?? $data['value'] ?? $data['_model_id'] ?? $fallbackId;
        $id = is_int($rawId) || is_string($rawId) ? $rawId : (string) $fallbackId;

        $rawTitle = $data['title'] ?? $data['label'] ?? $data['name'] ?? (string) $id;
        $title = is_scalar($rawTitle) ? (string) $rawTitle : (string) $id;

        $rawBadgeStyle = $data['badge_style'] ?? $data['badgeStyle'] ?? Style::DEFAULT;
        $badgeStyle = ($rawBadgeStyle instanceof Style || is_string($rawBadgeStyle))
            ? self::normalizeStyle($rawBadgeStyle)
            : Style::DEFAULT;

        $rawMetricStyle = $data['metric_style'] ?? $data['metricStyle'] ?? Style::DEFAULT;
        $metricStyle = ($rawMetricStyle instanceof Style || is_string($rawMetricStyle))
            ? self::normalizeStyle($rawMetricStyle)
            : Style::DEFAULT;

        $rawStyle = $data['style'] ?? Style::DEFAULT;
        $style = ($rawStyle instanceof Style || is_string($rawStyle))
            ? self::normalizeStyle($rawStyle)
            : Style::DEFAULT;

        $rawImageFit = $data['image_fit'] ?? $data['imageFit'] ?? 'cover';
        $imageFit = is_string($rawImageFit) ? self::normalizeImageFit($rawImageFit) : 'cover';

        $rawAttributes = isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : [];
        $rawTags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $rawActions = isset($data['actions']) && is_array($data['actions']) ? $data['actions'] : [];

        /** @var array<string, mixed> $clickParameters */
        $clickParameters = isset($data['click_parameters']) && is_array($data['click_parameters'])
            ? $data['click_parameters']
            : (isset($data['clickParameters']) && is_array($data['clickParameters']) ? $data['clickParameters'] : []);

        /** @var array<string, mixed> $meta */
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];

        $rawTarget = $data['target'] ?? '_self';
        $target = is_string($rawTarget) && trim($rawTarget) !== '' ? trim($rawTarget) : '_self';

        $rawImageStorage = self::extractNullableScalar($data, ['image_storage', 'imageStorage']);
        $imageStorage = ImageSourceResolver::normalizeStorageDirectory($rawImageStorage);

        $rawPlaceholder = $data['image_placeholder'] ?? $data['imagePlaceholder'] ?? null;
        $imagePlaceholder = is_bool($rawPlaceholder) ? $rawPlaceholder : null;

        $rawPlaceholderAspect = $data['image_placeholder_aspect'] ?? $data['imagePlaceholderAspect'] ?? null;
        $imagePlaceholderAspect = $rawPlaceholderAspect instanceof AspectRatio || is_string($rawPlaceholderAspect)
            ? ImageSourceResolver::normalizeAspectRatio($rawPlaceholderAspect)
            : null;

        return new self(
            id: $id,
            title: $title,
            subtitle: self::extractNullableScalar($data, ['subtitle']),
            eyebrow: self::extractNullableScalar($data, ['eyebrow', 'category']),
            description: self::extractNullableScalar($data, ['description']),
            image: self::extractNullableScalar($data, ['image', 'image_url', 'imageUrl']),
            imageAlt: self::extractNullableScalar($data, ['image_alt', 'imageAlt']),
            imageFit: $imageFit,
            aspectRatio: self::extractNullableScalar($data, ['aspect_ratio', 'aspectRatio']),
            icon: self::extractNullableScalar($data, ['icon']),
            avatar: self::extractNullableScalar($data, ['avatar']),
            badge: self::extractNullableScalar($data, ['badge']),
            badgeStyle: $badgeStyle,
            metric: self::extractNullableScalar($data, ['metric']),
            metricLabel: self::extractNullableScalar($data, ['metric_label', 'metricLabel']),
            metricStyle: $metricStyle,
            attributes: self::normalizeAttributes($rawAttributes),
            tags: self::normalizeTags($rawTags),
            footerText: self::extractNullableScalar($data, ['footer_text', 'footerText']),
            actions: self::normalizeActions($rawActions),
            clickAction: self::extractNullableScalar($data, ['click_action', 'clickAction']),
            clickParameters: $clickParameters,
            url: self::extractNullableScalar($data, ['url']),
            target: $target,
            style: $style,
            featured: (bool) ($data['featured'] ?? false),
            disabled: (bool) ($data['disabled'] ?? false),
            accentColor: self::extractNullableScalar($data, ['accent_color', 'accentColor']),
            meta: $meta,
            imageStorage: $imageStorage,
            imagePlaceholder: $imagePlaceholder,
            imagePlaceholderAspect: $imagePlaceholderAspect,
            imagePlaceholderId: self::extractNullableScalar($data, ['image_placeholder_id', 'imagePlaceholderId']),
            imagePlaceholderBackground: self::extractNullableScalar($data, ['image_placeholder_background', 'imagePlaceholderBackground']),
            imagePlaceholderText: self::extractNullableScalar($data, ['image_placeholder_text', 'imagePlaceholderText']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'title' => $this->title,
            'style' => $this->style->value,
            'badge_style' => $this->badgeStyle->value,
            'metric_style' => $this->metricStyle->value,
            'image_fit' => $this->imageFit,
            'featured' => $this->featured,
            'disabled' => $this->disabled,
        ];

        $optionalScalars = [
            'subtitle' => $this->subtitle,
            'eyebrow' => $this->eyebrow,
            'description' => $this->description,
            'image' => $this->image,
            'image_alt' => $this->imageAlt,
            'aspect_ratio' => $this->aspectRatio,
            'icon' => $this->icon,
            'avatar' => $this->avatar,
            'badge' => $this->badge,
            'metric' => $this->metric,
            'metric_label' => $this->metricLabel,
            'footer_text' => $this->footerText,
            'click_action' => $this->clickAction,
            'url' => $this->url,
            'accent_color' => $this->accentColor,
            'image_storage' => $this->imageStorage,
            'image_placeholder_aspect' => $this->imagePlaceholderAspect,
            'image_placeholder_id' => $this->imagePlaceholderId,
            'image_placeholder_background' => $this->imagePlaceholderBackground,
            'image_placeholder_text' => $this->imagePlaceholderText,
        ];

        foreach ($optionalScalars as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload[$key] = $value;
            }
        }

        if ($this->imagePlaceholder !== null) {
            $payload['image_placeholder'] = $this->imagePlaceholder;
        }

        if ($this->url !== null && $this->url !== '') {
            $payload['target'] = $this->target;
        }

        if ($this->clickParameters !== []) {
            $payload['click_parameters'] = $this->clickParameters;
        }

        if ($this->attributes !== []) {
            $payload['attributes'] = $this->attributes;
        }

        if ($this->tags !== []) {
            $payload['tags'] = $this->tags;
        }

        if ($this->actions !== []) {
            $payload['actions'] = array_map(
                static fn (CardActionDTO $action): array => $action->toArray(),
                $this->actions
            );
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

    private static function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private static function extractNullableScalar(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function normalizeStyle(Style|string $style): Style
    {
        if ($style instanceof Style) {
            return $style;
        }

        return Style::tryFrom(strtolower(trim($style))) ?? Style::DEFAULT;
    }

    /**
     * @return 'cover'|'contain'|'scale-down'
     */
    private static function normalizeImageFit(string $fit): string
    {
        return match (strtolower(trim($fit))) {
            'contain' => 'contain',
            'scale-down' => 'scale-down',
            default => 'cover',
        };
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<array{label: string, value: string, icon?: string}>
     */
    private static function normalizeAttributes(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $key => $entry) {
            if (is_array($entry)) {
                $rawLabel = $entry['label'] ?? (is_string($key) ? $key : '');
                $rawValue = $entry['value'] ?? '';
                $label = is_scalar($rawLabel) ? trim((string) $rawLabel) : '';
                $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
                if ($label === '' && $value === '') {
                    continue;
                }

                $item = [
                    'label' => $label,
                    'value' => $value,
                ];

                if (isset($entry['icon']) && is_scalar($entry['icon']) && trim((string) $entry['icon']) !== '') {
                    $item['icon'] = trim((string) $entry['icon']);
                }

                $normalized[] = $item;
            } elseif (is_string($key) && is_scalar($entry) && trim((string) $entry) !== '') {
                $normalized[] = [
                    'label' => trim($key),
                    'value' => trim((string) $entry),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<string>
     */
    private static function normalizeTags(array $raw): array
    {
        $tags = [];

        foreach ($raw as $tag) {
            if (is_scalar($tag)) {
                $trimmed = trim((string) $tag);
                if ($trimmed !== '') {
                    $tags[] = $trimmed;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<CardActionDTO>
     */
    private static function normalizeActions(array $raw): array
    {
        $actions = [];

        foreach ($raw as $entry) {
            if ($entry instanceof CardActionDTO) {
                $actions[] = $entry;
            } elseif (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $dto = CardActionDTO::fromArray($entry);
                if ($dto->label !== '' || $dto->icon !== null) {
                    $actions[] = $dto;
                }
            }
        }

        return $actions;
    }
}
