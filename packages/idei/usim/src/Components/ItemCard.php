<?php

namespace Idei\Usim\Components;

use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\Enums\Style;

/**
 * Builder for standardized ItemCard UI components (`type: 'item_card'`).
 *
 * First-class USIM card component that works both:
 * - Standalone inside any Screen, Container, Modal, or `.layout.yaml` (modern replacement for deprecated `Card`)
 * - Managed as child components inside `CardGrid` with native `ComponentDiffer` incremental diffs
 *
 * Uses closed, typed visual slots (no raw HTML) and semantic `Style` tokens
 * for full Light/Dark theme compatibility.
 */
class ItemCard extends UIComponent
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->type = 'item_card';
        $this->config['type'] = 'item_card';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            // Identity & Grid Ordering
            'item_id' => null,
            'card_order' => null,

            // Header & Body Slots
            'title' => '',
            'subtitle' => null,
            'eyebrow' => null,
            'description' => null,

            // Visual / Media
            'image' => null,
            'image_alt' => null,
            'image_fit' => 'cover',
            'aspect_ratio' => '16/9',
            'icon' => null,
            'avatar' => null,

            // Badge & Highlight Metric
            'badge' => null,
            'badge_style' => Style::DEFAULT->value,
            'metric' => null,
            'metric_label' => null,
            'metric_style' => Style::DEFAULT->value,

            // Attributes, Tags & Footer
            'attributes' => [],
            'tags' => [],
            'footer_text' => null,
            'actions' => [],

            // Interaction
            'click_action' => null,
            'click_parameters' => [],
            'url' => null,
            'target' => '_self',

            // Presentation & Semantic Styling
            'style' => Style::DEFAULT->value,
            'featured' => false,
            'disabled' => false,
            'accent_color' => null,
            'layout' => 'vertical', // 'vertical' | 'horizontal'
            'variant' => 'elevated', // 'elevated' | 'outlined' | 'flat'
            'density' => 'comfortable', // 'compact' | 'comfortable' | 'spacious'
            'max_visible_actions' => 2,
            'max_visible_tags' => 3,

            // Extra metadata
            'meta' => [],
        ];
    }

    // ─── Identity & Ordering ──────────────────────────────────────────────────

    public function itemId(int|string|null $id): static
    {
        return $this->setConfig('item_id', $id);
    }

    public function getItemId(): int|string|null
    {
        $raw = $this->config['item_id'] ?? null;

        return is_int($raw) || is_string($raw) ? $raw : null;
    }

    public function getItemKey(): string
    {
        $itemId = $this->getItemId();

        return $itemId !== null ? (string) $itemId : (string) $this->id;
    }

    public function cardOrder(?int $order): static
    {
        return $this->setConfig('card_order', $order !== null ? max(0, $order) : null);
    }

    public function getCardOrder(): int
    {
        $raw = $this->config['card_order'] ?? null;

        return is_numeric($raw) ? max(0, (int) $raw) : 0;
    }

    // ─── Content Slots ────────────────────────────────────────────────────────

    public function title(string $title): static
    {
        return $this->setConfig('title', $title);
    }

    public function subtitle(?string $subtitle): static
    {
        return $this->setConfig('subtitle', self::nullableTrim($subtitle));
    }

    public function eyebrow(?string $eyebrow): static
    {
        return $this->setConfig('eyebrow', self::nullableTrim($eyebrow));
    }

    public function description(?string $description): static
    {
        return $this->setConfig('description', self::nullableTrim($description));
    }

    public function image(?string $url, ?string $alt = null, string $fit = 'cover', ?string $aspectRatio = null): static
    {
        $this->setConfig('image', self::nullableTrim($url));
        if ($alt !== null) {
            $this->setConfig('image_alt', self::nullableTrim($alt));
        }
        $this->setConfig('image_fit', strtolower(trim($fit)) === 'contain' ? 'contain' : 'cover');
        if ($aspectRatio !== null) {
            $this->aspectRatio($aspectRatio);
        }

        return $this;
    }

    public function imageAlt(?string $alt): static
    {
        return $this->setConfig('image_alt', self::nullableTrim($alt));
    }

    public function imageFit(string $fit): static
    {
        return $this->setConfig('image_fit', strtolower(trim($fit)) === 'contain' ? 'contain' : 'cover');
    }

    public function aspectRatio(?string $ratio): static
    {
        $clean = self::nullableTrim($ratio);

        return $this->setConfig('aspect_ratio', $clean ?? '16/9');
    }

    public function icon(?string $icon): static
    {
        return $this->setConfig('icon', self::nullableTrim($icon));
    }

    public function avatar(?string $avatar): static
    {
        return $this->setConfig('avatar', self::nullableTrim($avatar));
    }

    public function badge(?string $badge, Style|string|null $style = null): static
    {
        $this->setConfig('badge', self::nullableTrim($badge));
        if ($style !== null) {
            $this->badgeStyle($style);
        }

        return $this;
    }

    public function badgeStyle(Style|string $style): static
    {
        return $this->setConfig('badge_style', self::resolveStyle($style));
    }

    public function metric(?string $metric, ?string $label = null, Style|string|null $style = null): static
    {
        $this->setConfig('metric', self::nullableTrim($metric));
        if ($label !== null) {
            $this->metricLabel($label);
        }
        if ($style !== null) {
            $this->metricStyle($style);
        }

        return $this;
    }

    public function metricLabel(?string $label): static
    {
        return $this->setConfig('metric_label', self::nullableTrim($label));
    }

    public function metricStyle(Style|string $style): static
    {
        return $this->setConfig('metric_style', self::resolveStyle($style));
    }

    /**
     * @param  array<int|string, mixed>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $normalized = [];

        foreach ($attributes as $key => $entry) {
            if (is_array($entry)) {
                $rawLabel = $entry['label'] ?? (is_string($key) ? $key : '');
                $rawValue = $entry['value'] ?? '';
                $label = is_scalar($rawLabel) ? trim((string) $rawLabel) : '';
                $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
                if ($label === '' && $value === '') {
                    continue;
                }

                $item = ['label' => $label, 'value' => $value];
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

        return $this->setConfig('attributes', $normalized);
    }

    /**
     * @param  array<int, mixed>  $tags
     */
    public function tags(array $tags): static
    {
        $clean = [];
        foreach ($tags as $tag) {
            if (is_scalar($tag)) {
                $trimmed = trim((string) $tag);
                if ($trimmed !== '') {
                    $clean[] = $trimmed;
                }
            }
        }

        return $this->setConfig('tags', array_values(array_unique($clean)));
    }

    public function footerText(?string $text): static
    {
        return $this->setConfig('footer_text', self::nullableTrim($text));
    }

    // ─── Actions & Click Interaction ──────────────────────────────────────────

    /**
     * Replace all card footer/overflow actions.
     *
     * @param  array<int, CardActionDTO|array<string, mixed>>  $actions
     */
    public function actions(array $actions): static
    {
        $normalized = [];
        foreach ($actions as $entry) {
            if ($entry instanceof CardActionDTO) {
                $normalized[] = $entry->toArray();
            } elseif (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $dto = CardActionDTO::fromArray($entry);
                if ($dto->label !== '' || $dto->icon !== null) {
                    $normalized[] = $dto->toArray();
                }
            }
        }

        return $this->setConfig('actions', $normalized);
    }

    /**
     * Append a single action button (or overflow menu item) to the card footer.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function addAction(
        string $label,
        ?string $action = null,
        array $parameters = [],
        ?string $icon = null,
        Style|string $style = Style::DEFAULT,
        string $variant = 'outline',
        bool $disabled = false,
        bool $overflow = false,
        ?string $url = null,
        string $target = '_self',
        ?string $tooltip = null
    ): static {
        $existing = is_array($this->config['actions'] ?? null) ? $this->config['actions'] : [];
        $existing[] = CardActionDTO::make(
            label: $label,
            action: $action,
            parameters: $parameters,
            url: $url,
            target: $target,
            icon: $icon,
            style: $style,
            variant: $variant,
            disabled: $disabled,
            overflow: $overflow,
            tooltip: $tooltip,
        )->toArray();

        return $this->setConfig('actions', array_values($existing));
    }

    /**
     * Make the card clickable with a Screen action (`POST /api/ui-event`).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function clickAction(?string $action, array $parameters = []): static
    {
        $this->setConfig('click_action', self::nullableTrim($action));
        $this->setConfig('click_parameters', $parameters);

        return $this;
    }

    /**
     * Alias for clickAction() for standalone ergonomics.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onClick(?string $action, array $parameters = []): static
    {
        return $this->clickAction($action, $parameters);
    }

    public function url(?string $url, string $target = '_self'): static
    {
        $this->setConfig('url', self::nullableTrim($url));
        $this->setConfig('target', trim($target) !== '' ? trim($target) : '_self');

        return $this;
    }

    // ─── Presentation & Semantic Style ────────────────────────────────────────

    public function style(Style|string $style): static
    {
        return $this->setConfig('style', self::resolveStyle($style));
    }

    public function featured(bool $featured = true): static
    {
        return $this->setConfig('featured', $featured);
    }

    public function disabled(bool $disabled = true): static
    {
        return $this->setConfig('disabled', $disabled);
    }

    public function accentColor(?string $color): static
    {
        return $this->setConfig('accent_color', self::nullableTrim($color));
    }

    public function layout(string $layout): static
    {
        return $this->setConfig('layout', strtolower(trim($layout)) === 'horizontal' ? 'horizontal' : 'vertical');
    }

    public function vertical(): static
    {
        return $this->layout('vertical');
    }

    public function horizontal(): static
    {
        return $this->layout('horizontal');
    }

    public function variant(string $variant): static
    {
        $normalized = match (strtolower(trim($variant))) {
            'outlined' => 'outlined',
            'flat' => 'flat',
            default => 'elevated',
        };

        return $this->setConfig('variant', $normalized);
    }

    public function density(string $density): static
    {
        $normalized = match (strtolower(trim($density))) {
            'compact' => 'compact',
            'spacious' => 'spacious',
            default => 'comfortable',
        };

        return $this->setConfig('density', $normalized);
    }

    public function maxVisibleActions(int $max): static
    {
        return $this->setConfig('max_visible_actions', max(0, min(5, $max)));
    }

    public function maxVisibleTags(int $max): static
    {
        return $this->setConfig('max_visible_tags', max(0, min(10, $max)));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): static
    {
        return $this->setConfig('meta', $meta);
    }

    // ─── DTO Hydration & Partial Patching ─────────────────────────────────────

    /**
     * Synchronize all presentation slots from a CardItemDTO.
     *
     * Because USIM's `ComponentDiffer` diffs every property individually, calling
     * this on an already-mounted `ItemCard` produces zero diff for unchanged fields
     * and emits only changed properties.
     *
     * @param  array<string, mixed>  $defaultClickParameters
     */
    public function applyFromDTO(
        CardItemDTO $dto,
        ?string $defaultClickAction = null,
        array $defaultClickParameters = [],
        ?string $defaultAspectRatio = null,
    ): static {
        $this->itemId($dto->id);
        $this->title($dto->title);
        $this->subtitle($dto->subtitle);
        $this->eyebrow($dto->eyebrow);
        $this->description($dto->description);
        $this->setConfig('image', $dto->image);
        $this->setConfig('image_alt', $dto->imageAlt);
        $this->setConfig('image_fit', $dto->imageFit);
        $this->setConfig('aspect_ratio', $dto->aspectRatio ?? $defaultAspectRatio ?? '16/9');
        $this->setConfig('icon', $dto->icon);
        $this->setConfig('avatar', $dto->avatar);
        $this->setConfig('badge', $dto->badge);
        $this->setConfig('badge_style', $dto->badgeStyle->value);
        $this->setConfig('metric', $dto->metric);
        $this->setConfig('metric_label', $dto->metricLabel);
        $this->setConfig('metric_style', $dto->metricStyle->value);
        $this->setConfig('attributes', $dto->attributes);
        $this->setConfig('tags', $dto->tags);
        $this->setConfig('footer_text', $dto->footerText);
        $this->actions($dto->actions);

        $clickAction = $dto->clickAction;
        $clickParams = $dto->clickParameters;
        if ($clickAction === null && $dto->url === null && $defaultClickAction !== null && $defaultClickAction !== '') {
            $clickAction = $defaultClickAction;
            $clickParams = array_merge($defaultClickParameters, ['id' => $dto->id]);
        }

        $this->setConfig('click_action', $clickAction);
        $this->setConfig('click_parameters', $clickParams);
        $this->setConfig('url', $dto->url);
        $this->setConfig('target', $dto->target);
        $this->setConfig('style', $dto->style->value);
        $this->setConfig('featured', $dto->featured);
        $this->setConfig('disabled', $dto->disabled);
        $this->setConfig('accent_color', $dto->accentColor);
        $this->setConfig('meta', $dto->meta);

        return $this;
    }

    /**
     * Apply a partial associative array of changed properties onto this ItemCard.
     * Supports camelCase and snake_case keys, Style enums, CardActionDTOs, and explicit nulls.
     *
     * @param  array<string, mixed>  $changes
     */
    public function patch(array $changes): static
    {
        $keyMap = [
            'badgeStyle' => 'badge_style',
            'metricLabel' => 'metric_label',
            'metricStyle' => 'metric_style',
            'imageAlt' => 'image_alt',
            'imageFit' => 'image_fit',
            'aspectRatio' => 'aspect_ratio',
            'footerText' => 'footer_text',
            'clickAction' => 'click_action',
            'clickParameters' => 'click_parameters',
            'accentColor' => 'accent_color',
        ];

        $styleKeys = ['style', 'badge_style', 'metric_style'];

        foreach ($changes as $rawKey => $value) {
            $key = $keyMap[$rawKey] ?? $rawKey;

            if (in_array($key, $styleKeys, true)) {
                $resolved = ($value instanceof Style || is_string($value))
                    ? self::resolveStyle($value)
                    : Style::DEFAULT->value;
                $this->setConfig($key, $resolved);

                continue;
            }

            if ($key === 'actions' && is_array($value)) {
                /** @var array<int, CardActionDTO|array<string, mixed>> $value */
                $this->actions($value);

                continue;
            }

            if ($key === 'attributes' && is_array($value)) {
                $this->attributes($value);

                continue;
            }

            if ($key === 'tags' && is_array($value)) {
                /** @var array<int, mixed> $value */
                $this->tags($value);

                continue;
            }

            $this->setConfig($key, $value);
        }

        return $this;
    }

    private static function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function resolveStyle(Style|string $style): string
    {
        if ($style instanceof Style) {
            return $style->value;
        }

        $parsed = Style::tryFrom(strtolower(trim($style)));

        return $parsed !== null ? $parsed->value : Style::DEFAULT->value;
    }
}
