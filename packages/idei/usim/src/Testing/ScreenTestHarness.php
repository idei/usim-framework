<?php

namespace Idei\Usim\Testing;

use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\Style;
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
        /** @phpstan-var T $instance */
        $instance = \is_string($screen) ? Screen::make($screen) : $screen;

        return new self($instance, $storage, $query);
    }

    /**
     * Set incoming storage (e.g. store_* variables).
     *
     * @param  array<string, mixed>  $storage
     * @return self<TScreen>
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
     * @return self<TScreen>
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
     * @return self<TScreen>
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
     * @param  string  $action
     * @param  array<string, mixed>  $parameters
     * @return self<TScreen>
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
     * Click an interactive component (e.g. Button) by name, triggering its configured action.
     *
     * @param  string  $name
     * @param  array<string, mixed>  $parameters
     * @return self<TScreen>
     */
    public function click(string $name, array $parameters = []): self
    {
        $component = $this->getComponent($name);

        $action = method_exists($component, 'get') ? $component->get('action') : null;
        if (! is_string($action) || trim($action) === '') {
            $screenClass = $this->screen::class;
            throw new \InvalidArgumentException("Component [{$name}] on Screen [{$screenClass}] does not have an action configured to click.");
        }

        $componentParams = method_exists($component, 'get') ? ($component->get('parameters') ?? []) : [];
        if (! is_array($componentParams)) {
            $componentParams = [];
        }

        /** @var array<string, mixed> $componentParams */
        $mergedParameters = array_merge($componentParams, $parameters);

        return $this->call($action, $mergedParameters);
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

    // ─── CardGrid & ItemCard Helpers ──────────────────────────────────────────

    /**
     * Retrieve a CardGrid component by name, failing if missing or not a CardGrid.
     */
    public function getCardGrid(string $gridName): CardGrid
    {
        $component = $this->getComponent($gridName);
        $screenClass = $this->screen::class;
        Assert::assertInstanceOf(
            CardGrid::class,
            $component,
            "Component [{$gridName}] on Screen [{$screenClass}] is not a CardGrid."
        );

        return $component;
    }

    /**
     * Find a mounted child ItemCard inside a CardGrid by domain item ID.
     */
    public function findCard(string $gridName, int|string $itemId): ?ItemCard
    {
        $grid = $this->findComponent($gridName);
        if (! $grid instanceof CardGrid) {
            return null;
        }

        return $grid->card($itemId);
    }

    /**
     * Retrieve a mounted child ItemCard inside a CardGrid, failing if not found.
     */
    public function getCard(string $gridName, int|string $itemId): ItemCard
    {
        $grid = $this->getCardGrid($gridName);
        $card = $grid->card($itemId);
        $screenClass = $this->screen::class;

        Assert::assertNotNull(
            $card,
            "Card [{$itemId}] was not found in CardGrid [{$gridName}] on Screen [{$screenClass}]."
        );

        return $card;
    }

    /**
     * Assert that a card with the given item ID is currently mounted in the CardGrid.
     *
     * @return self<TScreen>
     */
    public function assertHasCard(string $gridName, int|string $itemId): self
    {
        $this->getCard($gridName, $itemId);

        return $this;
    }

    /**
     * Assert that no card with the given item ID is currently mounted in the CardGrid.
     *
     * @return self<TScreen>
     */
    public function assertMissingCard(string $gridName, int|string $itemId): self
    {
        $grid = $this->getCardGrid($gridName);
        Assert::assertNull(
            $grid->card($itemId),
            "Expected card [{$itemId}] to be absent from CardGrid [{$gridName}], but it was found."
        );

        return $this;
    }

    /**
     * Assert the number of currently mounted ItemCards in a CardGrid.
     *
     * @return self<TScreen>
     */
    public function assertCardCount(string $gridName, int $expected): self
    {
        $grid = $this->getCardGrid($gridName);
        $actual = count($grid->getCards());
        Assert::assertSame(
            $expected,
            $actual,
            "Expected CardGrid [{$gridName}] to have {$expected} mounted cards, got {$actual}."
        );

        return $this;
    }

    /**
     * Assert a card's badge text (and optionally its badge style).
     *
     * @return self<TScreen>
     */
    public function assertCardBadge(
        string $gridName,
        int|string $itemId,
        ?string $expectedBadge,
        Style|string|null $expectedStyle = null
    ): self {
        $card = $this->getCard($gridName, $itemId);
        $actualBadge = $card->get('badge');

        Assert::assertSame(
            $expectedBadge,
            $actualBadge,
            "Expected card [{$itemId}] in CardGrid [{$gridName}] to have badge [" . var_export($expectedBadge, true) . '], got [' . var_export($actualBadge, true) . '].'
        );

        if ($expectedStyle !== null) {
            $expectedStyleValue = $expectedStyle instanceof Style ? $expectedStyle->value : strtolower(trim($expectedStyle));
            $actualStyle = $card->get('badge_style');
            Assert::assertSame(
                $expectedStyleValue,
                $actualStyle,
                "Expected card [{$itemId}] in CardGrid [{$gridName}] to have badge style [{$expectedStyleValue}], got [" . var_export($actualStyle, true) . '].'
            );
        }

        return $this;
    }

    /**
     * Assert that a card exposes a configured action with the given action name.
     *
     * @return self<TScreen>
     */
    public function assertCardHasAction(string $gridName, int|string $itemId, string $action): self
    {
        $card = $this->getCard($gridName, $itemId);
        $matched = $this->findActionOnCard($card, $action);

        Assert::assertNotNull(
            $matched,
            "Expected card [{$itemId}] in CardGrid [{$gridName}] to have action [{$action}], but it was not found."
        );

        return $this;
    }

    /**
     * Assert that a card does NOT expose a configured action with the given action name.
     *
     * @return self<TScreen>
     */
    public function assertCardMissingAction(string $gridName, int|string $itemId, string $action): self
    {
        $card = $this->getCard($gridName, $itemId);
        $matched = $this->findActionOnCard($card, $action);

        Assert::assertNull(
            $matched,
            "Expected card [{$itemId}] in CardGrid [{$gridName}] NOT to have action [{$action}], but it was found."
        );

        return $this;
    }

    /**
     * Click a specific action button (inline or overflow) on a mounted CardGrid ItemCard,
     * merging its configured parameters and default `id` exactly as UsimItemCardComponent does.
     *
     * @param  array<string, mixed>  $parameters
     * @return self<TScreen>
     */
    public function clickCardAction(string $gridName, int|string $itemId, string $action, array $parameters = []): self
    {
        $card = $this->getCard($gridName, $itemId);
        $matched = $this->findActionOnCard($card, $action);

        Assert::assertNotNull(
            $matched,
            "Action [{$action}] was not found on card [{$itemId}] in CardGrid [{$gridName}]."
        );

        if ((bool) ($matched['disabled'] ?? false)) {
            throw new \InvalidArgumentException(
                "Action [{$action}] on card [{$itemId}] in CardGrid [{$gridName}] is disabled and cannot be clicked."
            );
        }

        $actionParams = isset($matched['parameters']) && is_array($matched['parameters'])
            ? $matched['parameters']
            : [];

        /** @var array<string, mixed> $actionParams */
        if (! array_key_exists('id', $actionParams) && $card->getItemId() !== null) {
            $actionParams['id'] = $card->getItemId();
        }

        return $this->call($action, array_merge($actionParams, $parameters));
    }

    /**
     * Trigger the card-level click action (`click_action`) on a mounted CardGrid ItemCard.
     *
     * @param  array<string, mixed>  $parameters
     * @return self<TScreen>
     */
    public function clickCard(string $gridName, int|string $itemId, array $parameters = []): self
    {
        $card = $this->getCard($gridName, $itemId);

        if ((bool) ($card->get('disabled') ?? false)) {
            throw new \InvalidArgumentException(
                "Card [{$itemId}] in CardGrid [{$gridName}] is disabled and cannot be clicked."
            );
        }

        $clickAction = $card->get('click_action');
        if (! is_string($clickAction) || trim($clickAction) === '') {
            throw new \InvalidArgumentException(
                "Card [{$itemId}] in CardGrid [{$gridName}] does not have a click_action configured."
            );
        }

        $clickParams = $card->get('click_parameters');
        if (! is_array($clickParams)) {
            $clickParams = [];
        }

        /** @var array<string, mixed> $clickParams */
        if (! array_key_exists('id', $clickParams) && $card->getItemId() !== null) {
            $clickParams['id'] = $card->getItemId();
        }

        return $this->call($clickAction, array_merge($clickParams, $parameters));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActionOnCard(ItemCard $card, string $action): ?array
    {
        $actions = $card->get('actions');
        if (! is_array($actions)) {
            return null;
        }

        foreach ($actions as $entry) {
            if (is_array($entry) && ($entry['action'] ?? null) === $action) {
                /** @var array<string, mixed> $entry */
                return $entry;
            }
        }

        return null;
    }

    /**
     * Assert a component exists in the screen tree.
     *
     * @return self<TScreen>
     */
    public function assertHasComponent(string $name): self
    {
        $this->getComponent($name);

        return $this;
    }

    /**
     * Alias for assertHasComponent.
     *
     * @return self<TScreen>
     */
    public function assertComponentExists(string $name): self
    {
        return $this->assertHasComponent($name);
    }

    /**
     * Assert a component has a specific value.
     *
     * @return self<TScreen>
     */
    public function assertComponentValue(string $name, mixed $expected): self
    {
        $component = $this->getComponent($name);
        $actual = method_exists($component, 'getValue') ? $component->getValue() : null;
        Assert::assertSame($expected, $actual, "Expected component [{$name}] value to be [" . var_export($expected, true) . '], got [' . var_export($actual, true) . '].');

        return $this;
    }

    /**
     * Assert a component has a specific text.
     *
     * @return self<TScreen>
     */
    public function assertComponentText(string $name, string $expected): self
    {
        $component = $this->getComponent($name);
        $actual = null;
        if (method_exists($component, 'getText')) {
            $actual = $component->getText();
        }
        if ($actual === null && method_exists($component, 'get')) {
            $val = $component->get('text');
            $actual = is_string($val) ? $val : null;
        }
        Assert::assertSame($expected, $actual, "Expected component [{$name}] text to be [{$expected}], got [" . var_export($actual, true) . '].');

        return $this;
    }

    /**
     * Assert the screen emitted a redirect.
     *
     * @param  string|null  $expectedUrl
     * @return self<TScreen>
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
     * @return self<TScreen>
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
     * @param  string|null  $message
     * @param  string|null  $type
     *
     * @return self<TScreen>
     */
    public function assertToast(?string $message = null, ?string $type = null): self
    {
        $collector = $this->getChanges();
        Assert::assertTrue(
            $collector->hasToast($message, $type),
            "Expected toast with message [{$message}] and type [{$type}], but none matched in: " . json_encode($collector->getToasts())
        );

        return $this;
    }

    /**
     * Assert the screen emitted no toast notifications.
     *
     * @return self<TScreen>
     */
    public function assertNoToast(): self
    {
        $collector = $this->getChanges();
        Assert::assertEmpty(
            $collector->getToasts(),
            'Expected no toast notifications, but found: ' . json_encode($collector->getToasts())
        );

        return $this;
    }

    /**
     * Assert a modal was requested.
     *
     * @param  string|null  $modalClass
     * @return self<TScreen>
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
     * @return self<TScreen>
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
     * @param  string  $key
     * @param  mixed|null  $expected
     * @return self<TScreen>
     */
    public function assertStorageHas(string $key, mixed $expected = null): self
    {
        $storage = $this->storage;
        Assert::assertArrayHasKey($key, $storage, "Storage does not contain key [{$key}].");

        if ($expected !== null) {
            Assert::assertSame($expected, $storage[$key], "Expected storage key [{$key}] to equal [" . var_export($expected, true) . '], got [' . var_export($storage[$key], true) . '].');
        }

        return $this;
    }

    /**
     * Dynamically call actions on the screen via harness:
     * $harness->submit_login($params)
     *
     * @param  string  $name
     * @param  array<int, mixed>  $arguments
     * @return self<TScreen>
     */
    public function __call(string $name, array $arguments): self
    {
        $parameters = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];

        return $this->call($name, $parameters);
    }
}
