<?php

namespace Idei\Usim\Components;

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
 * - Color customization: text, background, border, and icon colors
 * - Icons and badges for visual enhancement
 * - Semantic HTML tags (span, p, h1-h6, strong, em, code, etc.)
 * - Interactive: clickable with optional actions and tooltips
 * - Visual effects: animations, shadow, glow
 * - Layout control: inline/block, width constraints
 * - Full accessibility: ARIA labels, roles, semantic structure
 */
class Label extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            // Core content
            'text' => '',
            'html' => null, // Allow HTML content if needed
            'markdown' => false, // Render text using markdown formatting

            // Style and appearance
            'style' => 'default', // default, primary, secondary, success, danger, warning, info, muted
            'variant' => 'text', // text, badge, chip, tag, pill, outlined
            'size' => 'medium', // xs, small, medium, large, xl

            // Typography
            'font_weight' => 'normal', // normal, bold, semibold, light, medium
            'font_size' => null, // Custom font size
            'text_transform' => 'none', // none, uppercase, lowercase, capitalize
            'text_align' => 'left', // left, center, right, justify
            'line_height' => null, // Custom line height
            'truncate' => false, // Truncate with ellipsis
            'max_lines' => null, // Clamp to N lines

            // Color customization
            'color' => null, // Custom text color
            'background_color' => null, // Custom background color
            'border_color' => null, // Custom border color

            // Icon
            'icon' => null,
            'icon_position' => 'left', // left, right
            'icon_color' => null,

            // Badge/Counter
            'badge' => null, // Show a small badge/counter
            'badge_style' => 'danger',

            // Semantic HTML
            'tag' => 'span', // span, p, h1, h2, h3, h4, h5, h6, strong, em, small, mark, code, pre
            'for' => null, // For label element (links to input id)

            // Interaction
            'clickable' => false,
            'action' => null, // Action when clicked
            'tooltip' => null,

            // Visual effects
            'animation' => null, // fade, slide, bounce, pulse
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
     * @param string $text The text to display
     * @return $this For method chaining
     */
    public function text(string $text): self
    {
        return $this->setConfig('text', __($text));
    }

    /**
     * Set HTML content (use with caution)
     *
     * If the first argument matches an existing backend view name,
     * it renders the view and stores the resulting HTML.
     *
     * @param string $htmlOrView Raw HTML string or view name (dot notation)
     * @param array $data Optional data for the view
     * @return $this For method chaining
     */
    public function html(string $htmlOrView, array $data = []): self
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
    public function markdown(bool $enabled = true): self
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
     * @param string $style The style name (default, primary, secondary, success, danger, warning, info, muted)
     * @return $this For method chaining
     */
    public function style(string $style): self
    {
        return $this->setConfig('style', $style);
    }

    public function primary(): self
    {
        return $this->setConfig('style', 'primary');
    }

    public function secondary(): self
    {
        return $this->setConfig('style', 'secondary');
    }

    public function success(): self
    {
        return $this->setConfig('style', 'success');
    }

    public function danger(): self
    {
        return $this->setConfig('style', 'danger');
    }

    public function warning(): self
    {
        return $this->setConfig('style', 'warning');
    }

    public function info(): self
    {
        return $this->setConfig('style', 'info');
    }
    public function muted(): self
    {
        return $this->setConfig('style', 'muted');
    }

    public function default(): self
    {
        return $this->setConfig('style', 'default');
    }

    public function h1(): self
    {
        return $this->setConfig('style', 'h1');
    }

    public function h2(): self
    {
        return $this->setConfig('style', 'h2');
    }

    public function h3(): self
    {
        return $this->setConfig('style', 'h3');
    }

    public function h4(): self
    {
        return $this->setConfig('style', 'h4');
    }

    /**
     * Set the label variant
     *
     * @param string $variant The variant (text, badge, chip, tag, pill, outlined)
     * @return $this For method chaining
     */
    public function variant(string $variant): self
    {
        return $this->setConfig('variant', $variant);
    }

    /**
     * Set the label size
     *
     * @param string $size The size (xs, small, medium, large, xl)
     * @return $this For method chaining
     */
    public function size(string $size): self
    {
        return $this->setConfig('size', $size);
    }

    /**
     * Set the font weight
     *
     * @param string $weight The weight (normal, bold, semibold, light, medium)
     * @return $this For method chaining
     */
    public function fontWeight(string $weight): self
    {
        return $this->setConfig('font_weight', $weight);
    }

    /**
     * Make text bold
     *
     * @return $this For method chaining
     */
    public function bold(): self
    {
        return $this->setConfig('font_weight', 'bold');
    }

    /**
     * Set custom font size
     *
     * @param string $size The font size (e.g., '14px', '1rem')
     * @return $this For method chaining
     */
    public function fontSize(string $size): self
    {
        return $this->setConfig('font_size', $size);
    }

    /**
     * Set text transformation
     *
     * @param string $transform The transform (none, uppercase, lowercase, capitalize)
     * @return $this For method chaining
     */
    public function textTransform(string $transform): self
    {
        return $this->setConfig('text_transform', $transform);
    }

    /**
     * Make text uppercase
     *
     * @return $this For method chaining
     */
    public function uppercase(): self
    {
        return $this->setConfig('text_transform', 'uppercase');
    }

    /**
     * Make text lowercase
     *
     * @return $this For method chaining
     */
    public function lowercase(): self
    {
        return $this->setConfig('text_transform', 'lowercase');
    }

    /**
     * Capitalize text
     *
     * @return $this For method chaining
     */
    public function capitalize(): self
    {
        return $this->setConfig('text_transform', 'capitalize');
    }

    /**
     * Set text alignment
     *
     * @param string $align The alignment (left, center, right, justify)
     * @return $this For method chaining
     */
    public function textAlign(string $align): self
    {
        return $this->setConfig('text_align', $align);
    }

    /**
     * Center align text
     *
     * @return $this For method chaining
     */
    public function center(): self
    {
        return $this->setConfig('text_align', 'center');
    }

    /**
     * Set text alignment to left
     *
     * @return $this For method chaining
     */
    public function left(): self
    {
        return $this->setConfig('text_align', 'left');
    }

    /**
     * Set text alignment to right
     *
     * @return $this For method chaining
     */
    public function right(): self
    {
        return $this->setConfig('text_align', 'right');
    }

    /**
     * Set custom line height
     *
     * @param string $height The line height (e.g., '1.5', '24px')
     * @return $this For method chaining
     */
    public function lineHeight(string $height): self
    {
        return $this->setConfig('line_height', $height);
    }

    /**
     * Truncate text with ellipsis
     *
     * @param bool $truncate True to enable truncation
     * @return $this For method chaining
     */
    public function truncate(bool $truncate = true): self
    {
        return $this->setConfig('truncate', $truncate);
    }

    /**
     * Limit text to N lines
     *
     * @param int $lines Number of lines
     * @return $this For method chaining
     */
    public function maxLines(int $lines): self
    {
        return $this->setConfig('max_lines', $lines);
    }

    /**
     * Set custom text color
     *
     * @param string $color The color (e.g., '#FF0000', 'red')
     * @return $this For method chaining
     */
    public function color(string $color): self
    {
        return $this->setConfig('color', $color);
    }

    /**
     * Set custom background color
     *
     * @param string $color The background color
     * @return $this For method chaining
     */
    public function backgroundColor(string $color): self
    {
        return $this->setConfig('background_color', $color);
    }

    /**
     * Set custom border color
     *
     * @param string $color The border color
     * @return $this For method chaining
     */
    public function borderColor(string $color): self
    {
        return $this->setConfig('border_color', $color);
    }

    /**
     * Set an icon
     *
     * @param string $icon The icon name
     * @param string $position The position (left, right)
     * @return $this For method chaining
     */
    public function icon(string $icon, string $position = 'left'): self
    {
        $this->setConfig('icon', $icon);
        $this->setConfig('icon_position', $position);
        return $this;
    }

    /**
     * Set icon color
     *
     * @param string $color The icon color
     * @return $this For method chaining
     */
    public function iconColor(string $color): self
    {
        return $this->setConfig('icon_color', $color);
    }

    /**
     * Add a badge/counter
     *
     * @param string|int $badge The badge content
     * @param string $style The badge style
     * @return $this For method chaining
     */
    public function badge(string|int $badge, string $style = 'danger'): self
    {
        $this->setConfig('badge', $badge);
        $this->setConfig('badge_style', $style);
        return $this;
    }

    /**
     * Set the HTML tag
     *
     * @param string $tag The HTML tag (span, p, h1-h6, strong, em, small, mark, code, pre)
     * @return $this For method chaining
     */
    public function tag(string $tag): self
    {
        return $this->setConfig('tag', $tag);
    }

    /**
     * Make this a heading (h1-h6)
     *
     * @param int $level The heading level (1-6)
     * @return $this For method chaining
     */
    public function heading(int $level): self
    {
        return $this->setConfig('tag', 'h' . min(6, max(1, $level)));
    }

    /**
     * Make this a paragraph
     *
     * @return $this For method chaining
     */
    public function paragraph(): self
    {
        return $this->setConfig('tag', 'p');
    }

    /**
     * Make this strong (bold emphasis)
     *
     * @return $this For method chaining
     */
    public function strong(): self
    {
        return $this->setConfig('tag', 'strong');
    }

    /**
     * Make this code
     *
     * @return $this For method chaining
     */
    public function code(): self
    {
        return $this->setConfig('tag', 'code');
    }

    /**
     * Set the 'for' attribute (for label elements)
     *
     * @param string $forId The ID of the associated input
     * @return $this For method chaining
     */
    public function forInput(string $forId): self
    {
        $this->setConfig('for', $forId);
        $this->setConfig('tag', 'label');
        return $this;
    }

    /**
     * Make label clickable
     *
     * @param string|null $action Optional action to trigger
     * @return $this For method chaining
     */
    public function clickable(?string $action = null): self
    {
        $this->setConfig('clickable', true);
        if ($action !== null) {
            $this->setConfig('action', $action);
        }
        return $this;
    }

    /**
     * Set tooltip
     *
     * @param string $tooltip The tooltip text
     * @return $this For method chaining
     */
    public function tooltip(string $tooltip): self
    {
        return $this->setConfig('tooltip', $tooltip);
    }

    /**
     * Set animation
     *
     * @param string $animation The animation (fade, slide, bounce, pulse)
     * @return $this For method chaining
     */
    public function animation(string $animation): self
    {
        return $this->setConfig('animation', $animation);
    }

    /**
     * Enable shadow effect
     *
     * @param bool $shadow True to enable shadow
     * @return $this For method chaining
     */
    public function shadow(bool $shadow = true): self
    {
        return $this->setConfig('shadow', $shadow);
    }

    /**
     * Enable glow effect
     *
     * @param bool $glow True to enable glow
     * @return $this For method chaining
     */
    public function glow(bool $glow = true): self
    {
        return $this->setConfig('glow', $glow);
    }

    /**
     * Set display mode
     *
     * @param bool $inline True for inline, false for block
     * @return $this For method chaining
     */
    public function inline(bool $inline = true): self
    {
        return $this->setConfig('inline', $inline);
    }

    /**
     * Make block display
     *
     * @return $this For method chaining
     */
    public function block(): self
    {
        return $this->setConfig('inline', false);
    }

    /**
     * Set fixed width
     *
     * @param string $width The width (e.g., '200px', '50%')
     * @return $this For method chaining
     */
    public function width(string $width): static
    {
        return $this->setConfig('width', $width);
    }

    /**
     * Set maximum width
     *
     * @param string $maxWidth The max width
     * @return $this For method chaining
     */
    public function maxWidth(string $maxWidth): static
    {
        return $this->setConfig('max_width', $maxWidth);
    }

    /**
     * Set ARIA label
     *
     * @param string $label The ARIA label
     * @return $this For method chaining
     */
    public function ariaLabel(string $label): self
    {
        return $this->setConfig('aria_label', $label);
    }

    /**
     * Set ARIA role
     *
     * @param string $role The ARIA role
     * @return $this For method chaining
     */
    public function role(string $role): self
    {
        return $this->setConfig('role', $role);
    }
}
