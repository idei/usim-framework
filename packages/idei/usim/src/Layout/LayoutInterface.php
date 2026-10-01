<?php

namespace Idei\Usim\Layout;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;

interface LayoutInterface
{
    /**
     * Register a container as a named slot.
     */
    public function registerSlot(string $name, Container $container): Container;

    /**
     * Get a registered slot container by name.
     */
    public function getSlot(string $name = 'content'): ?Container;

    /**
     * Get all registered slots.
     *
     * @return array<string, Container>
     */
    public function getSlots(): array;

    /**
     * Set the active screen class or slug occupying a slot.
     *
     * @param  class-string<Screen>|string  $screenClass
     */
    public function setActiveScreen(string $slot, string $screenClass): self;

    /**
     * Get the active screen class or slug currently occupying a slot.
     *
     * @return class-string<Screen>|string|null
     */
    public function getActiveScreen(string $slot = 'main'): ?string;

    /**
     * Mount and display a target screen inside a layout slot.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Target slot name (e.g. 'main', 'top_menu', 'sidebar')
     * @param  array<int|string, mixed>  $params  Parameters passed to the screen
     * @param  bool  $updateBrowserUrl  Whether to push HTML5 browser history state
     */
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true): bool;

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param  class-string<Screen>|string  $screenClass
     * @param  array<int|string, mixed>  $params
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool;
}
