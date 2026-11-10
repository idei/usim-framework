<?php

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class ModernScreenWithUpdateUI extends Screen
{
    public string $status = 'initial';
    public ?string $receivedName = null;

    protected function startingUI(): Container
    {
        return UI::container('main');
    }

    protected function updateUI(string $name = 'default'): void
    {
        $this->status = 'updated';
        $this->receivedName = $name;
    }

    public function triggerLegacyCall(): void
    {
        // Calling legacy postLoadUI internally should delegate to updateUI via __call
        $this->postLoadUI('called_via_legacy');
    }
}

class LegacyScreenWithPostLoadUI extends Screen
{
    public string $status = 'initial';
    public ?string $receivedName = null;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::label('lbl'));
    }

    protected function postLoadUI(string $name = 'default'): void
    {
        $this->status = 'postLoaded';
        $this->receivedName = $name;
    }

    public function triggerModernCall(): void
    {
        // Calling modern updateUI internally should delegate to postLoadUI via __call
        $this->updateUI('called_via_modern');
    }
}

class CustomLayoutWithUpdateUI extends AbstractLayout
{
    public bool $customLayoutUpdated = false;

    public function setStateActiveScreens(array $screens): void
    {
        $this->state_active_screens = $screens;
    }

    public function getRawActiveScreens(): array
    {
        return $this->activeScreens;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $content = UI::container('content_container');
        $this->contentContainer = $content;
        $container->add($content);
    }

    protected function updateUI(): void
    {
        $this->customLayoutUpdated = true;
    }
}

it('invokes updateUI on screen via callPostLoadUI and callUpdateUI with resolved parameters', function () {
    $screen = new ModernScreenWithUpdateUI();

    expect($screen->status)->toBe('initial');

    $screen->callPostLoadUI(['name' => 'John']);
    expect($screen->status)->toBe('updated');
    expect($screen->receivedName)->toBe('John');

    $screen->status = 'reset';
    $screen->callUpdateUI(['name' => 'Alice']);
    expect($screen->status)->toBe('updated');
    expect($screen->receivedName)->toBe('Alice');
});

it('invokes postLoadUI on legacy screen via callPostLoadUI and callUpdateUI', function () {
    $screen = new LegacyScreenWithPostLoadUI();

    expect($screen->status)->toBe('initial');

    $screen->callUpdateUI(['name' => 'Bob']);
    expect($screen->status)->toBe('postLoaded');
    expect($screen->receivedName)->toBe('Bob');
});

it('delegates internal call to updateUI when postLoadUI is called on screen that only has updateUI', function () {
    $screen = new ModernScreenWithUpdateUI();
    $screen->triggerLegacyCall();

    expect($screen->status)->toBe('updated');
    expect($screen->receivedName)->toBe('called_via_legacy');
});

it('delegates internal call to postLoadUI when updateUI is called on screen that only has postLoadUI', function () {
    $screen = new LegacyScreenWithPostLoadUI();
    $screen->triggerModernCall();

    expect($screen->status)->toBe('postLoaded');
    expect($screen->receivedName)->toBe('called_via_modern');
});

it('executes both AbstractLayout::postLoadUI and subclass updateUI in layout screens', function () {
    $layout = new CustomLayoutWithUpdateUI();
    $container = UI::container('root');
    $content = UI::container('content_container');
    $container->add($content);
    $layout->setContainer($container);
    $layout->setStateActiveScreens(['content' => 'App\\UI\\Screens\\Demo\\FormDemo']);

    expect($layout->customLayoutUpdated)->toBeFalse();
    expect($layout->getRawActiveScreens())->toBeEmpty();

    $layout->callUpdateUI();

    // AbstractLayout::postLoadUI restored active screens into activeScreens array
    expect($layout->getRawActiveScreens()['content'])->toBe('App\\UI\\Screens\\Demo\\FormDemo');
    // CustomLayoutWithUpdateUI::updateUI ran as well
    expect($layout->customLayoutUpdated)->toBeTrue();
});

it('does not dispatch lifecycle methods as action handlers via __call', function () {
    $emptyScreen = new class extends Screen {
        protected function buildBaseUI(Container $container, ...$params): void
        {
        }
    };

    expect($emptyScreen->startingUI())->toBeNull();
    expect($emptyScreen->buildBaseUI())->toBeNull();
    expect($emptyScreen->updateUI())->toBeNull();
    expect($emptyScreen->postLoadUI())->toBeNull();
});
