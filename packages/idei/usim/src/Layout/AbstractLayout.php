<?php

namespace Idei\Usim\Layout;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;

abstract class AbstractLayout
{
    protected ?Container $mainMenuContainer = null;
    protected ?Container $contentContainer = null;

    /** @var array<string, Container> */
    protected array $slots = [];

    /**
     * Build the layout structure wrapping the screen content slot.
     *
     * @param Container $root Root container of the host screen
     * @param Closure(Container): void $contentBuilder Callback that builds the screen content inside the slot
     * @param class-string<Screen>|null $menuScreen Custom menu screen class if declared by the host screen
     */
    abstract public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void;

    /**
     * Register a container as a named slot.
     */
    public function registerSlot(string $name, Container $container): Container
    {
        return $this->slots[$name] = $container;
    }

    /**
     * Get a registered slot container by name.
     */
    public function getSlot(string $name): ?Container
    {
        return $this->slots[$name] ?? match ($name) {
            'menu', 'main_menu', 'top_menu' => $this->mainMenuContainer,
            'main', 'center', 'content' => $this->contentContainer,
            default => null,
        };
    }

    /**
     * Get all registered slots.
     *
     * @return array<string, Container>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    /**
     * Get the main menu container / slot.
     */
    public function getMainMenuContainer(): ?Container
    {
        return $this->mainMenuContainer;
    }

    /**
     * Get the content container / slot.
     */
    public function getContentContainer(): ?Container
    {
        return $this->contentContainer;
    }
}
