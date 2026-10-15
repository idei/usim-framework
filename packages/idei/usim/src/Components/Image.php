<?php

namespace Idei\Usim\Components;

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
}
