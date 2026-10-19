<?php

namespace Idei\Usim\Components;

use Idei\Usim\Contracts\ImagePlaceholderGeneratorInterface;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\ValueObjects\ImagePlaceholder;
use InvalidArgumentException;

/**
 * Image UI Component Builder
 *
 * Supports static images (via src) or dynamic asynchronous data fetching
 * (via action / GET /api/ui-data) with built-in loading spinner and disabled state.
 */
class Image extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            'src' => null,
            'alt' => '',
            'action' => null,
            'parameters' => [],
            'auto_fetch' => true,
            'width' => null,
            'height' => null,
            'object_fit' => 'cover',
            'border_radius' => null,
            'lazy' => true,
            'fallback_src' => null,
            'caption' => null,
            'border' => false,
            'shadow' => false,
            'placeholder_enabled' => true,
            'placeholder_aspect_ratio' => null,
            'placeholder_id' => null,
            'placeholder_background_color' => null,
            'placeholder_text_color' => null,
        ];
    }

    public function src(string $src): static
    {
        return $this->setConfig('src', $src);
    }

    public function alt(string $alt): static
    {
        return $this->setConfig('alt', $alt);
    }

    /**
     * Sets the action to fetch image data asynchronously from the server.
     * The action corresponds to a method in the screen class that returns the image URL or data
     *
     * @param  array<string, mixed>  $parameters
     */
    public function action(string $action, array $parameters = []): static
    {
        $this->setConfig('action', $action);
        if (! empty($parameters)) {
            $this->setConfig('parameters', $parameters);
        }

        return $this;
    }

    /**
     * Sets the parameters to be sent with the action request when fetching image data asynchronously.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): static
    {
        return $this->setConfig('parameters', $parameters);
    }

    public function autoFetch(bool $autoFetch = true): static
    {
        return $this->setConfig('auto_fetch', $autoFetch);
    }

    public function objectFit(string $fit): static
    {
        return $this->setConfig('object_fit', $fit);
    }

    public function rounded(int|string|bool $radius = true): static
    {
        if (is_bool($radius)) {
            $value = $radius ? '8px' : '0px';
        } elseif (is_numeric($radius)) {
            $value = "{$radius}px";
        } else {
            $value = (string) $radius;
        }

        return $this->setConfig('border_radius', $value);
    }

    public function circular(): static
    {
        return $this->setConfig('border_radius', '50%');
    }

    public function lazy(bool $lazy = true): static
    {
        return $this->setConfig('lazy', $lazy);
    }

    public function fallback(string $fallbackSrc): static
    {
        return $this->setConfig('fallback_src', $fallbackSrc);
    }

    public function placeholderEnabled(bool $enabled = true): static
    {
        return $this->setConfig('placeholder_enabled', $enabled);
    }

    public function placeholderAspect(AspectRatio $aspectRatio): static
    {
        return $this->setConfig('placeholder_aspect_ratio', $aspectRatio);
    }

    public function placeholderId(int|string $id): static
    {
        if (trim((string) $id) === '') {
            throw new InvalidArgumentException('Image placeholder ID must not be empty.');
        }

        return $this->setConfig('placeholder_id', (string) $id);
    }

    public function placeholderColors(string $background, string $text): static
    {
        return $this
            ->setConfig('placeholder_background_color', $background)
            ->setConfig('placeholder_text_color', $text);
    }

    public function caption(string $caption): static
    {
        return $this->setConfig('caption', __($caption));
    }

    public function border(bool|string $border = true): static
    {
        return $this->setConfig('border', $border);
    }

    public function shadow(bool $shadow = true): static
    {
        return $this->setConfig('shadow', $shadow);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toJson(?int $order = null): array
    {
        $json = parent::toJson($order);
        $component = reset($json);

        if (! is_array($component)) {
            return $json;
        }

        if ($this->shouldGeneratePlaceholder()) {
            $component['src'] = $this->generatePlaceholderSource();
        }

        unset(
            $component['placeholder_enabled'],
            $component['placeholder_aspect_ratio'],
            $component['placeholder_id'],
            $component['placeholder_background_color'],
            $component['placeholder_text_color'],
        );

        $json[$this->id] = $component;

        return $json;
    }

    private function shouldGeneratePlaceholder(): bool
    {
        $src = $this->config['src'] ?? null;

        return ($src === null || (is_string($src) && trim($src) === ''))
            && (bool) ($this->config['placeholder_enabled'] ?? true)
            && (bool) config('usim.images.placeholder.enabled', true);
    }

    private function generatePlaceholderSource(): string
    {
        $settings = $this->placeholderSettings();
        $configuredWidth = $this->pixelDimension($this->config['width'] ?? null);
        $configuredHeight = $this->pixelDimension($this->config['height'] ?? null);
        $aspectRatio = $this->config['placeholder_aspect_ratio'] ?? null;

        if ($aspectRatio instanceof AspectRatio) {
            if ($configuredWidth === null && $configuredHeight === null) {
                $configuredWidth = $this->positiveSetting($settings, 'width', 640);
            }

            [$width, $height] = $aspectRatio->dimensionsFor($configuredWidth, $configuredHeight);
        } else {
            $width = $configuredWidth ?? $this->positiveSetting($settings, 'width', 640);
            $height = $configuredHeight ?? $this->positiveSetting($settings, 'height', 360);
        }

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image placeholder dimensions must be positive integers.');
        }

        $placeholderId = $this->config['placeholder_id'] ?? $this->name ?? $this->id;
        if (! is_string($placeholderId) && ! is_int($placeholderId)) {
            throw new InvalidArgumentException('Image placeholder ID must be a string or integer.');
        }

        return app(ImagePlaceholderGeneratorInterface::class)->generate(new ImagePlaceholder(
            id: (string) $placeholderId,
            width: $width,
            height: $height,
            caption: is_string($this->config['caption'] ?? null) ? $this->config['caption'] : null,
            backgroundColor: $this->stringSetting(
                $this->config['placeholder_background_color'] ?? null,
                $settings['background_color'] ?? null,
                '#e2e8f0'
            ),
            textColor: $this->stringSetting(
                $this->config['placeholder_text_color'] ?? null,
                $settings['text_color'] ?? null,
                '#475569'
            ),
            seedPrefix: $this->stringSetting($settings['seed_prefix'] ?? null, null, '123'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderSettings(): array
    {
        $settings = config('usim.images.placeholder', []);
        if (! is_array($settings)) {
            throw new InvalidArgumentException('USIM image placeholder settings must be an array.');
        }

        $stringKeyedSettings = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('USIM image placeholder settings must use string keys.');
            }
            $stringKeyedSettings[$key] = $value;
        }

        return $stringKeyedSettings;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function positiveSetting(array $settings, string $key, int $default): int
    {
        $value = $settings[$key] ?? $default;
        if (! is_int($value) || $value <= 0) {
            throw new InvalidArgumentException("USIM image placeholder setting [{$key}] must be a positive integer.");
        }

        return $value;
    }

    private function stringSetting(mixed $value, mixed $fallback, string $default): string
    {
        $setting = $value ?? $fallback ?? $default;
        if (! is_string($setting)) {
            throw new InvalidArgumentException('USIM image placeholder text and color settings must be strings.');
        }

        return $setting;
    }

    private function pixelDimension(mixed $dimension): ?int
    {
        if (! is_string($dimension) && ! is_int($dimension)) {
            return null;
        }

        if (is_int($dimension)) {
            return $dimension > 0 ? $dimension : null;
        }

        if (preg_match('/^([1-9][0-9]*)px$/', trim($dimension), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
