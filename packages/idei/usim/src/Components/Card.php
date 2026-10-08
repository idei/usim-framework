<?php

namespace Idei\Usim\Components;

use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;

/**
 * Card component builder
 *
 * Flexible card component for displaying structured content with support for
 * titles, images, actions, badges, and multiple visual styles. Supports both
 * clickable and static modes with customizable layout and appearance.
 */
class Card extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            // Core content
            'title' => null,
            'subtitle' => null,
            'description' => null,
            'content' => null, // HTML content

            // Image
            'image' => null, // Image URL
            'image_position' => 'top', // top, bottom, left, right, background
            'image_alt' => null,
            'image_fit' => 'cover', // cover, contain, fill, scale-down

            // Header
            'header' => null, // Custom header content
            'show_header' => true,

            // Footer/Actions
            'footer' => null, // Custom footer content
            'show_footer' => true,
            'actions' => [], // Array of button configurations

            // Visual style
            'style' => 'default', // default, outlined, elevated, flat, gradient
            'variant' => 'standard', // standard, compact, expanded, media
            'size' => 'medium', // small, medium, large
            'elevation' => 'medium', // none, low, medium, high

            // Layout
            'orientation' => 'vertical', // vertical, horizontal
            'content_padding' => 'medium', // none, small, medium, large
            'border_radius' => 'medium', // none, small, medium, large, round

            // Colors and theming
            'background_color' => null,
            'border_color' => null,
            'text_color' => null,
            'theme' => null, // primary, secondary, success, warning, danger, info

            // Interaction
            'clickable' => false,
            'hover_effect' => true,
            'action' => null, // Action when card is clicked
            'parameters' => [],
            'url' => null, // URL for navigation
            'target' => '_self', // Link target

            // Badge/Status
            'badge' => null,
            'badge_position' => 'top-right', // top-left, top-right, bottom-left, bottom-right
            'status' => null, // Status indicator

            // Accessibility
            'aria_label' => null,
            'role' => 'article', // article, button, link, etc.
        ];
    }

    /**
     * Set the card title
     *
     * @param  string  $title  Card title
     */
    public function title(string $title): static
    {
        return $this->setConfig('title', __($title));
    }

    /**
     * Set the card subtitle
     *
     * @param  string  $subtitle  Card subtitle
     */
    public function subtitle(string $subtitle): static
    {
        return $this->setConfig('subtitle', __($subtitle));
    }

    /**
     * Set the card description
     *
     * @param  string  $description  Card description
     */
    public function description(string $description): static
    {
        return $this->setConfig('description', __($description));
    }

    /**
     * Set card content (HTML)
     *
     * @param  string  $content  HTML content
     */
    public function content(string $content): static
    {
        return $this->setConfig('content', __($content));
    }

    /**
     * Set card image
     *
     * @param  string  $imageUrl  Image URL
     * @param  string  $position  Image position (top, bottom, left, right, background)
     * @param  string  $alt  Alt text for accessibility
     */
    public function image(string $imageUrl, string $position = 'top', ?string $alt = null): static
    {
        return $this->setConfig('image', $imageUrl)
            ->setConfig('image_position', $position)
            ->setConfig('image_alt', $alt);
    }

    /**
     * Set card style
     *
     * @param  string  $style  Style variant (default, outlined, elevated, flat, gradient)
     */
    public function style(string $style): static
    {
        return $this->setConfig('style', $style);
    }

    /**
     * Set card size
     *
     * @param  string  $size  Size variant (small, medium, large)
     */
    public function size(string $size): static
    {
        return $this->setConfig('size', $size);
    }

    /**
     * Set card theme
     *
     * @param  string  $theme  Theme color (primary, secondary, success, warning, danger, info)
     */
    public function theme(string $theme): static
    {
        return $this->setConfig('theme', $theme);
    }

    /**
     * Make card clickable with action
     *
     * @param  string  $action  Action to trigger
     * @param  array<string, mixed>  $parameters  Action parameters
     */
    public function action(string $action, array $parameters = []): static
    {
        return $this->setConfig('clickable', true)
            ->setConfig('action', $action)
            ->setConfig('parameters', $parameters);
    }

    /**
     * Make card clickable with URL navigation
     *
     * @param  string  $url  URL to navigate to
     * @param  string  $target  Link target (_self, _blank, etc.)
     */
    public function url(string $url, string $target = '_self'): static
    {
        return $this->setConfig('clickable', true)
            ->setConfig('url', $url)
            ->setConfig('target', $target);
    }

    /**
     * Add action buttons to card footer
     *
     * @param  list<array<string, mixed>>  $actions  Array of button configurations
     */
    public function actions(array $actions): static
    {
        return $this->setConfig('actions', $actions);
    }

    /**
     * Add an action button to the card footer
     *
     * @param  string  $label  Button label
     * @param  string  $action  Action to trigger
     * @param  array<string, mixed>  $parameters  Action parameters
     * @param  string  $style  Button style
     */
    public function addAction(string $label, string $action, array $parameters = [], string $style = 'primary'): static
    {
        // Inject caller service ID if not already present
        if (! isset($parameters['_caller_service_id'])) {
            // Get the service class that's calling this component
            $serviceClass = $this->detectCallingService();

            // Get the service ID (offset) directly from UIIdGenerator
            $serviceId = UIIdGenerator::getContextOffset($serviceClass);

            $parameters['_caller_service_id'] = $serviceId;
        }

        $currentActions = [];
        $existingActions = $this->config['actions'] ?? null;

        if (is_array($existingActions)) {
            foreach ($existingActions as $existingAction) {
                if (is_array($existingAction)) {
                    $currentActions[] = $existingAction;
                }
            }
        }

        $currentActions[] = [
            'label' => __($label),
            'action' => $action,
            'parameters' => $parameters,
            'style' => $style,
        ];

        return $this->setConfig('actions', $currentActions);
    }

    /**
     * Detect the calling service class from the stack trace
     *
     * @return string Service class name
     */
    private function detectCallingService(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($trace as $frame) {
            if (isset($frame['class']) &&
                is_subclass_of($frame['class'], Screen::class)) {
                return (string) $frame['class'];
            }
        }

        // If not found via subclass check, try standard recursive detection (fallback)
        // Similar logic to UIComponent::detectCallingContext but for framework internals.
        foreach ($trace as $frame) {
            if (isset($frame['class'])) {
                if (str_starts_with($frame['class'], 'App\\UI\\Components\\')) {
                    continue;
                }
                if (str_starts_with($frame['class'], 'Idei\\Usim\\')) {
                    continue;
                }
                if (str_starts_with($frame['class'], 'Idei\\Usim\\Http\\')) {
                    continue;
                }

                return $frame['class'];
            }
        }

        return 'default';
    }

    /**
     * Set card badge
     *
     * @param  string|int  $badge  Badge text or icon
     * @param  string  $position  Badge position (top-left, top-right, bottom-left, bottom-right)
     */
    public function badge(string|int $badge, string $position = 'top-right'): static
    {
        return $this->setConfig('badge', $badge)
            ->setConfig('badge_position', $position);
    }

    /**
     * Set horizontal orientation
     */
    public function horizontal(): static
    {
        return $this->setConfig('orientation', 'horizontal');
    }

    /**
     * Set vertical orientation
     */
    public function vertical(): static
    {
        return $this->setConfig('orientation', 'vertical');
    }

    /**
     * Enable hover effects
     *
     * @param  bool  $enabled  Whether to enable hover effects
     */
    public function hover(bool $enabled = true): static
    {
        return $this->setConfig('hover_effect', $enabled);
    }

    /**
     * Set compact variant
     */
    public function compact(): static
    {
        return $this->setConfig('variant', 'compact')
            ->setConfig('content_padding', 'small');
    }

    /**
     * Set expanded variant
     */
    public function expanded(): static
    {
        return $this->setConfig('variant', 'expanded')
            ->setConfig('content_padding', 'large');
    }

    /**
     * Show or hide the card header
     *
     * @param  bool  $show  Whether to show the header
     */
    public function showHeader(bool $show = true): static
    {
        return $this->setConfig('show_header', $show);
    }

    /**
     * Show or hide the card footer
     *
     * @param  bool  $show  Whether to show the footer
     */
    public function showFooter(bool $show = true): static
    {
        return $this->setConfig('show_footer', $show);
    }

    /**
     * Set border radius
     *
     * @param  string  $radius  Radius level (none, small, medium, large, round)
     */
    public function borderRadius(string $radius): static
    {
        return $this->setConfig('border_radius', $radius);
    }

    /**
     * Set content padding
     *
     * @param  string  $padding  Padding level (none, small, medium, large)
     */
    public function contentPadding(string $padding): static
    {
        return $this->setConfig('content_padding', $padding);
    }

    /**
     * Set card background color
     *
     * @param  string  $color  CSS color value
     */
    public function backgroundColor(string $color): static
    {
        return $this->setConfig('background_color', $color);
    }

    /**
     * Set card border color
     *
     * @param  string  $color  CSS color value
     */
    public function borderColor(string $color): static
    {
        return $this->setConfig('border_color', $color);
    }

    /**
     * Set card text color
     *
     * @param  string  $color  CSS color value
     */
    public function textColor(string $color): static
    {
        return $this->setConfig('text_color', $color);
    }

    /**
     * Set card status indicator
     *
     * @param  string  $status  Status value (success, warning, danger, info, etc.)
     */
    public function status(string $status): static
    {
        return $this->setConfig('status', $status);
    }

    /**
     * Set HTML role attribute
     *
     * @param  string  $role  HTML role (article, button, link, etc.)
     */
    public function role(string $role): static
    {
        return $this->setConfig('role', $role);
    }

    /**
     * Set image object-fit mode
     *
     * @param  string  $fit  Fit mode (cover, contain, fill, scale-down)
     */
    public function imageFit(string $fit): static
    {
        return $this->setConfig('image_fit', $fit);
    }

    /**
     * Set ARIA label for accessibility
     *
     * @param  string  $label  Accessible label text
     */
    public function ariaLabel(string $label): static
    {
        return $this->setConfig('aria_label', $label);
    }

    /**
     * Set card shadow (maps to elevation levels for CSS-class-based rendering).
     * Accepts numeric (0-3) or named values compatible with Container::shadow().
     *
     * @param  string|int  $intensity  0/'none', 1/'light', 2/'medium', 3/'heavy' or native 'low'/'high'
     */
    public function shadow(string|int $intensity = 1): static
    {
        $map = [
            0 => 'none',   'none' => 'none',
            1 => 'low',    'light' => 'low',   'low' => 'low',
            2 => 'medium', 'medium' => 'medium',
            3 => 'high',   'heavy' => 'high',  'high' => 'high',
        ];

        return $this->setConfig('elevation', $map[$intensity] ?? 'medium');
    }
}
