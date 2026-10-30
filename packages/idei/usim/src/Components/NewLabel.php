<?php

namespace Idei\Usim\Components;

use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\Animation;
use Idei\Usim\Enums\FontWeight;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\TextTransform;
use Idei\Usim\Enums\Variant;

/**
 * Builder for Label UI components
 *
 * Modern and versatile label component with rich styling options,
 * typography controls, icons, badges, and semantic HTML support.
 *
 * Features:
 * - Multiple content modes: text, HTML, markdown
 * - Rich styling: styles (default, primary, secondary, etc.), variants (badge, chip, tag, pill)
 * - Typography: font weight, size, transform, alignment, line height
 * - Icons and badges for visual enhancement
 * - Semantic HTML tags (span, p, h1-h6, strong, em, code, etc.)
 * - Interactive: clickable with optional actions and tooltips
 * - Visual effects: animations, shadow, glow
 * - Layout control: inline/block, width constraints
 * - Full accessibility: ARIA labels, roles, semantic structure
 */
class NewLabel extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            // Core content
            'text' => '',
            'html' => null, // Allow HTML content if needed
            'markdown' => false, // Render text using markdown formatting

            // Style and appearance
            'style' => Style::DEFAULT->value,
            'variant' => Variant::TEXT->value,
            'size' => TextSize::MEDIUM->value,

            // Typography
            'font_weight' => FontWeight::NORMAL->value,
            'text_transform' => TextTransform::NONE->value,
            'text_align' => Align::LEFT->value, // left, center, right, justify
            'truncate' => false, // Truncate with ellipsis
            'max_lines' => null, // Clamp to N lines

            // Icon
            'icon' => null,
            'icon_position' => Align::LEFT->value, // left, right

            // Semantic HTML
            'for' => null, // For label element (links to input id)

            // Interaction
            'tooltip' => null,

            // Visual effects
            'animation' => Animation::NONE->value, // fade, slide, bounce, pulse
            'shadow' => false, // Drop shadow
            'glow' => false, // Glow effect

            // Layout
            'inline' => true, // Display inline or block
            'width' => null, // Fixed width
            'max_width' => null, // Maximum width

            // Accessibility
            'aria_label' => null,
            'role' => null, // ARIA role
        ];
    }

    /**
     * Set the label text content
     *
     * @param  string  $text  The text to display
     * @return static For method chaining
     */
    public function text(string $text): static
    {
        return $this->setConfig('text', __($text));
    }

    public function getText(): ?string
    {
        $text = $this->get('text');

        return is_string($text) ? $text : null;
    }

    /**
     * Set HTML content (use with caution)
     *
     * If the first argument matches an existing backend view name,
     * it renders the view and stores the resulting HTML.
     *
     * @param  string  $htmlOrView  Raw HTML string or view name (dot notation)
     * @param  array<string, mixed>  $data  Optional data for the view
     * @return static For method chaining
     */
    public function html(string $htmlOrView, array $data = []): static
    {
        $value = trim($htmlOrView);

        if ($value !== '' && $this->isSafeViewName($value) && view()->exists($value)) {
            return $this->setConfig('html', view($value, $data)->render());
        }

        return $this->setConfig('html', $htmlOrView);
    }

    /**
     * Enable markdown rendering for text content.
     *
     * The renderer sanitizes text before converting markdown to HTML.
     */
    public function markdown(bool $enabled = true): static
    {
        return $this->setConfig('markdown', $enabled);
    }

    /**
     * Validate allowed view-name format.
     *
     * Accepts dot notation only (e.g. "welcome-usim", "emails.reset-password").
     */
    private function isSafeViewName(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1;
    }

    /**
     * Set the label style
     *
     * @param  Style|string  $style  The style (default, primary, secondary, success, danger, warning, info, muted)
     * @return static For method chaining
     */
    public function style(Style|string $style): static
    {
        $value = $style instanceof Style ? $style->value : $style;

        return $this->setConfig('style', $value);
    }

    public function primary(): static
    {
        return $this->style(Style::PRIMARY);
    }

    public function secondary(): static
    {
        return $this->style(Style::SECONDARY);
    }

    public function success(): static
    {
        return $this->style(Style::SUCCESS);
    }

    public function danger(): static
    {
        return $this->style(Style::DANGER);
    }

    public function warning(): static
    {
        return $this->style(Style::WARNING);
    }

    public function info(): static
    {
        return $this->style(Style::INFO);
    }

    public function muted(): static
    {
        return $this->style(Style::MUTED);
    }

    public function default(): static
    {
        return $this->style(Style::DEFAULT);
    }

    /**
     * Set the label variant
     *
     * @param  Variant|string  $variant  The variant (text, badge, chip, tag, pill, outlined)
     * @return static For method chaining
     */
    public function variant(Variant|string $variant): static
    {
        $value = $variant instanceof Variant ? $variant->value : $variant;

        return $this->setConfig('variant', $value);
    }

    public function badge(): static
    {
        return $this->variant(Variant::BADGE);
    }

    public function chip(): static
    {
        return $this->variant(Variant::CHIP);
    }

    public function tag(): static
    {
        return $this->variant(Variant::TAG);
    }

    public function pill(): static
    {
        return $this->variant(Variant::PILL);
    }

    public function outlined(): static
    {
        return $this->variant(Variant::OUTLINED);
    }

    /**
     * Set the label size
     *
     * @param  TextSize|string  $size  The size (xs, small, medium, large, xl)
     * @return static For method chaining
     */
    public function size(TextSize|string $size): static
    {
        $value = $size instanceof TextSize ? $size->value : $size;

        return $this->setConfig('size', $value);
    }

    public function xs(): static
    {
        return $this->size(TextSize::XS);
    }

    public function small(): static
    {
        return $this->size(TextSize::SMALL);
    }

    public function medium(): static
    {
        return $this->size(TextSize::MEDIUM);
    }

    public function large(): static
    {
        return $this->size(TextSize::LARGE);
    }

    public function xl(): static
    {
        return $this->size(TextSize::XL);
    }

    /**
     * Set the font weight
     *
     * @param  FontWeight|string  $weight
     * @return static For method chaining
     */
    public function fontWeight(FontWeight|string $weight): static
    {
        $value = $weight instanceof FontWeight ? $weight->value : $weight;

        return $this->setConfig('font_weight', $value);
    }

    public function light(): static
    {
        return $this->fontWeight(FontWeight::LIGHT);
    }

    public function normal(): static
    {
        return $this->fontWeight(FontWeight::NORMAL);
    }

    public function semibold(): static
    {
        return $this->fontWeight(FontWeight::SEMIBOLD);
    }

    /**
     * Make text bold
     *
     * @return static For method chaining
     */
    public function bold(): static
    {
        return $this->setConfig('font_weight', FontWeight::BOLD->value);
    }

    /**
     * Set text transformation
     *
     * @param  TextTransform|string  $transform  The transform (none, uppercase, lowercase, capitalize)
     * @return static For method chaining
     */
    public function textTransform(TextTransform|string $transform): static
    {
        $value = $transform instanceof TextTransform ? $transform->value : $transform;

        return $this->setConfig('text_transform', $value);
    }

    /**
     * Make text uppercase
     *
     * @return static For method chaining
     */
    public function uppercase(): static
    {
        return $this->setConfig('text_transform', TextTransform::UPPERCASE->value);
    }

    /**
     * Make text lowercase
     *
     * @return static For method chaining
     */
    public function lowercase(): static
    {
        return $this->setConfig('text_transform', TextTransform::LOWERCASE->value);
    }

    /**
     * Capitalize text
     *
     * @return static For method chaining
     */
    public function capitalize(): static
    {
        return $this->setConfig('text_transform', TextTransform::CAPITALIZE->value);
    }

    /**
     * Set text alignment
     *
     * @param  Align|string  $align
     * @return static For method chaining
     */
    public function textAlign(Align|string $align): static
    {
        $value = $align instanceof Align ? $align->value : $align;

        return $this->setConfig('text_align', $value);
    }

    /**
     * Center align text
     *
     * @return static For method chaining
     */
    public function center(): static
    {
        return $this->textAlign(Align::CENTER);
    }

    /**
     * Left align text
     *
     * @return static For method chaining
     */
    public function left(): static
    {
        return $this->textAlign(Align::LEFT);
    }

    /**
     * Right align text
     *
     * @return static For method chaining
     */
    public function right(): static
    {
        return $this->textAlign(Align::RIGHT);
    }

    /**
     * Truncate text with ellipsis
     *
     * @param  bool  $truncate  True to enable truncation
     * @return static For method chaining
     */
    public function truncate(bool $truncate = true): static
    {
        return $this->setConfig('truncate', $truncate);
    }

    /**
     * Limit text to N lines
     *
     * @param  int  $lines  Number of lines
     * @return static For method chaining
     */
    public function maxLines(int $lines): static
    {
        return $this->setConfig('max_lines', $lines);
    }

    /**
     * Set an icon
     *
     * @param  string  $icon  The icon name
     * @param  Align|string  $position  The position (left, right)
     * @return static For method chaining
     */
    public function icon(string $icon, Align|string $position = Align::LEFT): static
    {
        $pos = $position instanceof Align ? $position->value : $position;
        $this->setConfig('icon', $icon);
        $this->setConfig('icon_position', $pos);

        return $this;
    }

    /**
     * Set the 'for' attribute (for label elements)
     *
     * @param  string  $forId  The ID of the associated input
     * @return static For method chaining
     */
    public function forInput(string $forId): static
    {
        $this->setConfig('for', $forId);

        return $this;
    }

    /**
     * Set tooltip
     *
     * @param  string  $tooltip  The tooltip text
     * @return static For method chaining
     */
    public function tooltip(string $tooltip): static
    {
        return $this->setConfig('tooltip', $tooltip);
    }

    /**
     * Set animation
     *
     * @param  Animation|string  $animation  The animation
     * @return static For method chaining
     */
    public function animation(Animation|string $animation): static
    {
        $value = $animation instanceof Animation ? $animation->value : $animation;

        return $this->setConfig('animation', $value);
    }

    /**
     * Enable shadow effect
     *
     * @param  bool  $shadow  True to enable shadow
     * @return static For method chaining
     */
    public function shadow(bool $shadow = true): static
    {
        return $this->setConfig('shadow', $shadow);
    }

    /**
     * Enable glow effect
     *
     * @param  bool  $glow  True to enable glow
     * @return static For method chaining
     */
    public function glow(bool $glow = true): static
    {
        return $this->setConfig('glow', $glow);
    }

    /**
     * Set display mode
     *
     * @param  bool  $inline  True for inline, false for block
     * @return static For method chaining
     */
    public function inline(bool $inline = true): static
    {
        return $this->setConfig('inline', $inline);
    }

    /**
     * Make block display
     *
     * @return static For method chaining
     */
    public function block(): static
    {
        return $this->setConfig('inline', false);
    }

    /**
     * Set ARIA label
     *
     * @param  string  $label  The ARIA label
     * @return static For method chaining
     */
    public function ariaLabel(string $label): static
    {
        return $this->setConfig('aria_label', $label);
    }

    /**
     * Set ARIA role
     *
     * @param  string  $role  The ARIA role
     * @return static For method chaining
     */
    public function role(string $role): static
    {
        return $this->setConfig('role', $role);
    }
}
