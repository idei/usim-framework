<?php

namespace Idei\Usim\Components;

/**
 * Button UI Component Builder
 *
 * Fluent builder for creating interactive buttons with support for:
 * - Multiple styles (primary, secondary, success, danger, etc.)
 * - Visual variants (solid, outline, ghost, link)
 * - Icons with customizable position, size, and color
 * - Loading states with custom text and icons
 * - Confirmation dialogs before action execution
 * - Accessibility features (ARIA labels, keyboard shortcuts)
 * - Advanced styling (shapes, sizes, animations, ripple effects)
 * - State management (enabled, active, badge notifications)
 */
class Button extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            // Core functionality
            'label' => '',
            'action' => null,
            'parameters' => [],

            // State
            'enabled' => true,
            'loading' => false,
            'active' => false,

            // Visual style
            'style' => 'default', // default, primary, secondary, success, warning, danger, info, link, outline
            'variant' => 'solid', // solid, outline, ghost, link
            'no_background' => false,
            'no_hover' => false,
            'size' => 'medium', // small, medium, large, xs, xl
            'shape' => 'default', // default, rounded, pill, square, circle
            'fullWidth' => false,

            // Icon
            'icon' => null,
            'icon_color' => null,
            'icon_size' => null,
            'icon_position' => 'left', // left, right, top, bottom
            'icon_only' => false,

            // Interaction
            'tooltip' => null,
            'confirm_message' => null, // Show confirmation dialog before action
            'keyboard_shortcut' => null, // e.g., "Ctrl+S"
            'autofocus' => false,

            // Loading state
            'loading_text' => null, // Text to show when loading
            'loading_icon' => 'spinner',

            // Badge/Counter
            'badge' => null, // Notification badge
            'badge_style' => 'danger', // Style for the badge

            // Accessibility
            'aria_label' => null,
            'title' => null, // HTML title attribute

            // Animation
            'animation' => null, // pulse, bounce, shake, etc.
            'ripple_effect' => true,

            'status' => false, // Custom status for toggle buttons or stateful actions
        ];
    }

    /**
     * Set the button label text
     *
     * @param string $label The label text
     * @return static For method chaining
     */
    public function label(string $label): static
    {
        return $this->setConfig('label', $label);
    }

    /**
     * Set the action to trigger when button is clicked
     *
     * @param string $action The action name
     * @param array<string, mixed> $parameters Optional parameters for the action
     * @return static For method chaining
     */
    public function action(string $action, array $parameters = []): static
    {
        $this->setConfig('action', $action);
        $this->setConfig('parameters', $parameters);
        return $this;
    }

    /**
     * Set the button icon
     *
     * @param string $icon The icon name
     * @return static For method chaining
     */
    public function icon(string $icon): static
    {
        return $this->setConfig('icon', $icon);
    }

    /**
     * Set icon color for SVG icons
     *
     * @param string|null $color CSS color value (e.g. #ffffff, rgb(255,255,255), var(--color))
     * @return static For method chaining
     */
    public function iconColor(?string $color): static
    {
        return $this->setConfig('icon_color', $color);
    }

    /**
     * Set icon size for button icon
     *
     * @param string|int|null $size CSS size value (e.g. 16, "20px", "1.25rem")
     * @return static For method chaining
     */
    public function iconSize(string|int|null $size): static
    {
        return $this->setConfig('icon_size', $size);
    }

    /**
     * Set the button style
     *
     * @param string $style The style name (default, primary, secondary, success, danger, warning, info, link)
     * @return static For method chaining
     */
    public function style(string $style): static
    {
        return $this->setConfig('style', $style);
    }

    /**
     * Set whether the button is enabled
     *
     * @param bool $enabled True if enabled, false if disabled
     * @return static For method chaining
     */
    public function enabled(bool $enabled = true): static
    {
        return $this->setConfig('enabled', $enabled);
    }

    public function status(bool $status): self
    {
        return $this->setConfig('status', $status);
    }

    public function getStatus(): bool
    {
        return $this->config['status'];
    }

    public function toggle(): self
    {
        return $this->setConfig('status', !$this->config['status']);
    }

    /**
     * Disable the button
     *
     * @return static For method chaining
     */
    public function disabled(): static
    {
        return $this->setConfig('enabled', false);
    }

    /**
     * Set the button tooltip text
     *
     * @param string $tooltip The tooltip text
     * @return static For method chaining
     */
    public function tooltip(string $tooltip): static
    {
        return $this->setConfig('tooltip', $tooltip);
    }

    /**
     * Set the button size
     *
     * @param string $size The size (xs, small, medium, large, xl)
     * @return static For method chaining
     */
    public function size(string $size): static
    {
        return $this->setConfig('size', $size);
    }

    /**
     * Set the button variant
     *
     * @param string $variant The variant (solid, outline, ghost, link)
     * @return static For method chaining
     */
    public function variant(string $variant): static
    {
        return $this->setConfig('variant', $variant);
    }

    /**
     * Remove background color from button (transparent background)
     *
     * @param bool $noBackground True to use transparent background
     * @return static For method chaining
     */
    public function noBackground(bool $noBackground = true): static
    {
        return $this->setConfig('no_background', $noBackground);
    }

    /**
     * Disable hover visual effects (transform/shadow/background changes)
     *
     * @param bool $noHover True to disable hover effects
     * @return static For method chaining
     */
    public function noHover(bool $noHover = true): static
    {
        return $this->setConfig('no_hover', $noHover);
    }

    /**
     * Plain button preset: transparent background and no hover effects
     *
     * @param bool $plain True to enable plain style
     * @return static For method chaining
     */
    public function plain(bool $plain = true): static
    {
        $this->setConfig('no_background', $plain);
        $this->setConfig('no_hover', $plain);
        return $this;
    }

    /**
     * Set the button shape
     *
     * @param string $shape The shape (default, rounded, pill, square, circle)
     * @return static For method chaining
     */
    public function shape(string $shape): static
    {
        return $this->setConfig('shape', $shape);
    }

    /**
     * Set the icon position
     *
     * @param string $position The position (left, right, top, bottom)
     * @return static For method chaining
     */
    public function iconPosition(string $position): static
    {
        return $this->setConfig('icon_position', $position);
    }

    /**
     * Make this an icon-only button (no label)
     *
     * @param bool $iconOnly True for icon-only button
     * @return static For method chaining
     */
    public function iconOnly(bool $iconOnly = true): static
    {
        return $this->setConfig('icon_only', $iconOnly);
    }

    /**
     * Make the button full width
     *
     * @param bool $fullWidth True for full width
     * @return static For method chaining
     */
    public function fullWidth(bool $fullWidth = true): static
    {
        return $this->setConfig('fullWidth', $fullWidth);
    }

    /**
     * Set loading state
     *
     * @param bool $loading True if loading
     * @param string|null $loadingText Optional text to show while loading
     * @return static For method chaining
     */
    public function loading(bool $loading = true, ?string $loadingText = null): static
    {
        $this->setConfig('loading', $loading);
        if ($loadingText !== null) {
            $this->setConfig('loading_text', $loadingText);
        }
        return $this;
    }

    /**
     * Set active state (for toggle buttons)
     *
     * @param bool $active True if active
     * @return static For method chaining
     */
    public function active(bool $active = true): static
    {
        return $this->setConfig('active', $active);
    }

    /**
     * Add a confirmation message before executing action
     *
     * @param string $message The confirmation message
     * @return static For method chaining
     */
    public function confirm(string $message): static
    {
        return $this->setConfig('confirm_message', $message);
    }

    /**
     * Set keyboard shortcut
     *
     * @param string $shortcut The keyboard shortcut (e.g., "Ctrl+S", "Alt+N")
     * @return static For method chaining
     */
    public function shortcut(string $shortcut): static
    {
        return $this->setConfig('keyboard_shortcut', $shortcut);
    }

    /**
     * Set autofocus on this button
     *
     * @param bool $autofocus True to autofocus
     * @return static For method chaining
     */
    public function autofocus(bool $autofocus = true): static
    {
        return $this->setConfig('autofocus', $autofocus);
    }

    /**
     * Add a notification badge to the button
     *
     * @param string|int $badge The badge content (number or text)
     * @param string $style The badge style (default, primary, success, danger, warning, info)
     * @return static For method chaining
     */
    public function badge(string|int $badge, string $style = 'danger'): static
    {
        $this->setConfig('badge', $badge);
        $this->setConfig('badge_style', $style);
        return $this;
    }

    /**
     * Set ARIA label for accessibility
     *
     * @param string $label The ARIA label
     * @return static For method chaining
     */
    public function ariaLabel(string $label): static
    {
        return $this->setConfig('aria_label', $label);
    }

    /**
     * Set HTML title attribute
     *
     * @param string $title The title text
     * @return static For method chaining
     */
    public function title(string $title): static
    {
        return $this->setConfig('title', $title);
    }

    /**
     * Set loading icon
     *
     * @param string $icon The loading icon name
     * @return static For method chaining
     */
    public function loadingIcon(string $icon): static
    {
        return $this->setConfig('loading_icon', $icon);
    }

    /**
     * Set animation effect
     *
     * @param string $animation The animation name (pulse, bounce, shake, etc.)
     * @return static For method chaining
     */
    public function animation(string $animation): static
    {
        return $this->setConfig('animation', $animation);
    }

    /**
     * Enable/disable ripple effect
     *
     * @param bool $ripple True to enable ripple effect
     * @return static For method chaining
     */
    public function ripple(bool $ripple = true): static
    {
        return $this->setConfig('ripple_effect', $ripple);
    }
}
