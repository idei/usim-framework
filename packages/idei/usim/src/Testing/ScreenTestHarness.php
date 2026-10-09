<?php

namespace Idei\Usim\Testing;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Screen;
use Idei\Usim\UIChangesCollector;
use PHPUnit\Framework\Assert;

/**
 * Headless in-memory test harness for USIM Screens.
 *
 * Allows instantiating any Screen with injected mocks, rendering,
 * dispatching UI actions, and asserting UI changes in < 5ms without HTTP or database overhead.
 *
 * @template TScreen of Screen
 */
class ScreenTestHarness
{
    /** @var TScreen */
    protected Screen $screen;

    /** @var array<string, mixed> */
    protected array $storage = [];

    /** @var array<string, mixed> */
    protected array $query = [];

    /**
     * @param  TScreen  $screen
     * @param  array<string, mixed>  $storage
     * @param  array<string, mixed>  $query
     */
    public function __construct(Screen $screen, array $storage = [], array $query = [])
    {
        $this->screen = $screen;
        $this->storage = $storage;
        $this->query = $query;
    }

    /**
     * Create a harness for the given Screen class or instance.
     *
     * @template T of Screen
     * @param  class-string<T>|T  $screen
     * @param  array<string, mixed>  $storage
     * @param  array<string, mixed>  $query
     * @return ScreenTestHarness<T>
     */
    public static function for(string|Screen $screen, array $storage = [], array $query = []): self
    {
        /** @var T $instance */
        $instance = is_string($screen) ? Screen::make($screen) : $screen;

        return new self($instance, $storage, $query);
    }

    /**
     * Set incoming storage (e.g. store_* variables).
     *
     * @param  array<string, mixed>  $storage
     * @return $this
     */
    public function withStorage(array $storage): self
    {
        $this->storage = array_merge($this->storage, $storage);

        return $this;
    }

    /**
     * Set query parameters.
     *
     * @param  array<string, mixed>  $query
     * @return $this
     */
    public function withQuery(array $query): self
    {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Render the screen tree (initial view or reset).
     *
     * @param  array<int|string, mixed>  $buildParams
     * @param  bool  $shouldReset
     * @param  bool  $wrapLayout  Whether to wrap in layout if configured (default false for unit testing)
     * @return $this
     */
    public function render(array $buildParams = [], bool $shouldReset = false, bool $wrapLayout = false): self
    {
        $this->screen->render(
            incomingStorage: $this->storage,
            queryParams: $this->query,
            parent: $wrapLayout ? 'main' : null,
            shouldReset: $shouldReset,
            buildParams: $buildParams
        );

        return $this;
    }

    /**
     * Call an action handler on the screen by action name or method name.
     * E.g. ->call('submit_login', ['login_email' => '...'])
     *
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function call(string $action, array $parameters = []): self
    {
        $this->screen->callAction(
            action: $action,
            parameters: $parameters,
            incomingStorage: $this->storage,
            queryParams: $this->query
        );

        // Update local storage tracking from changes collector
        $this->storage = array_merge($this->storage, $this->screen->getUiChanges()->getStorage());

        return $this;
    }

    /**
     * Get the underlying Screen instance.
     *
     * @return TScreen
     */
    public function getScreen(): Screen
    {
        return $this->screen;
    }

    /**
     * Get the screen's UIChangesCollector.
     */
    public function getChanges(): UIChangesCollector
    {
        return $this->screen->getUiChanges();
    }

    /**
     * Get the screen container.
     */
    public function getContainer(): ?Container
    {
        return $this->screen->getContainer();
    }

    /**
     * Find a component inside the screen container by name.
     */
    public function findComponent(string $name): ?UIElement
    {
        $container = $this->getContainer();
        if ($container === null) {
            // Render first if not yet rendered
            $this->render();
            $container = $this->getContainer();
        }

        return $container?->findByName($name);
    }

    /**
     * Get a component by name, failing if not found.
     */
    public function getComponent(string $name): UIElement
    {
        $component = $this->findComponent($name);
        $screenClass = $this->screen::class;
        Assert::assertNotNull($component, "Component [{$name}] was not found in Screen [{$screenClass}].");

        return $component;
    }

    /**
     * Assert a component exists in the screen tree.
     *
     * @return $this
     */
    public function assertHasComponent(string $name): self
    {
        $this->getComponent($name);

        return $this;
    }

    /**
     * Assert a component has a specific value.
     *
     * @return $this
     */
    public function assertComponentValue(string $name, mixed $expected): self
    {
        $component = $this->getComponent($name);
        $actual = method_exists($component, 'getValue') ? $component->getValue() : null;
        Assert::assertSame($expected, $actual, "Expected component [{$name}] value to be [".var_export($expected, true).'], got ['.var_export($actual, true).'].');

        return $this;
    }

    /**
     * Assert a component has a specific text.
     *
     * @return $this
     */
    public function assertComponentText(string $name, string $expected): self
    {
        $component = $this->getComponent($name);
        $actual = method_exists($component, 'getText') ? $component->getText() : null;
        Assert::assertSame($expected, $actual, "Expected component [{$name}] text to be [{$expected}], got [".var_export($actual, true).'].');

        return $this;
    }

    /**
     * Assert the screen emitted a redirect.
     *
     * @return $this
     */
    public function assertRedirect(?string $expectedUrl = null): self
    {
        $collector = $this->getChanges();
        Assert::assertTrue(
            $collector->hasRedirect($expectedUrl),
            "Expected redirect to [{$expectedUrl}], but got [{$collector->getRedirect()}]."
        );

        return $this;
    }

    /**
     * Assert the screen emitted no redirect.
     *
     * @return $this
     */
    public function assertNoRedirect(): self
    {
        $collector = $this->getChanges();
        Assert::assertFalse(
            $collector->hasRedirect(),
            "Expected no redirect, but found redirect to [{$collector->getRedirect()}]."
        );

        return $this;
    }

    /**
     * Assert the screen emitted a toast notification.
     *
     * @return $this
     */
    public function assertToast(?string $message = null, ?string $type = null): self
    {
        $collector = $this->getChanges();
        Assert::assertTrue(
            $collector->hasToast($message, $type),
            "Expected toast with message [{$message}] and type [{$type}], but none matched in: ".json_encode($collector->getToasts())
        );

        return $this;
    }

    /**
     * Assert the screen emitted no toast notifications.
     *
     * @return $this
     */
    public function assertNoToast(): self
    {
        $collector = $this->getChanges();
        Assert::assertEmpty(
            $collector->getToasts(),
            'Expected no toast notifications, but found: '.json_encode($collector->getToasts())
        );

        return $this;
    }

    /**
     * Assert a modal was requested.
     *
     * @return $this
     */
    public function assertModal(?string $modalClass = null): self
    {
        $collector = $this->getChanges();
        Assert::assertTrue(
            $collector->hasModal($modalClass),
            "Expected modal [{$modalClass}] to be opened."
        );

        return $this;
    }

    /**
     * Assert modal was closed.
     *
     * @return $this
     */
    public function assertModalClosed(): self
    {
        $collector = $this->getChanges();
        Assert::assertTrue(
            $collector->isModalClosed(),
            'Expected modal to be closed.'
        );

        return $this;
    }

    /**
     * Assert storage contains key/value.
     *
     * @return $this
     */
    public function assertStorageHas(string $key, mixed $expected = null): self
    {
        $storage = $this->storage;
        Assert::assertArrayHasKey($key, $storage, "Storage does not contain key [{$key}].");

        if ($expected !== null) {
            Assert::assertSame($expected, $storage[$key], "Expected storage key [{$key}] to equal [".var_export($expected, true).'], got ['.var_export($storage[$key], true).'].');
        }

        return $this;
    }

    /**
     * Dynamically call actions on the screen via harness:
     * $harness->submit_login($params)
     *
     * @param  array<int, mixed>  $arguments
     * @return $this
     */
    public function __call(string $name, array $arguments): self
    {
        /** @var array<string, mixed> $parameters */
        $parameters = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];

        return $this->call($name, $parameters);
    }
}
