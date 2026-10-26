<?php

use App\Models\User;
use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Menu;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Idei\Usim\Components\Container;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->seed([GenreSeeder::class, MovieSeeder::class]);
    AbstractLayout::setCurrent(null);
    UIStateManager::setClientCurrentScreen('', null);
});

it('supports slot registration and retrieval on abstract layout', function () {
    /** @var TestCase $this */
    $layout = new class extends AbstractLayout
    {
        public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void
        {
            $slot = UI::container('sidebar_slot');
            $this->registerSlot('sidebar', $slot);

            $main = UI::container('main_slot');
            $this->registerSlot('main', $main);

            $root->add($slot)->add($main);
            $contentBuilder($main);
        }
    };

    $root = UI::container('root');
    $layout->build($root, fn ($c) => null);

    expect($layout->getSlot('sidebar'))->not->toBeNull();
    expect($layout->getSlot('sidebar')?->getName())->toBe('sidebar_slot');
    expect($layout->getSlot('main')?->getName())->toBe('main_slot');
    expect($layout->getSlot('non_existent'))->toBeNull();
    expect(array_keys($layout->getSlots()))->toContain('sidebar', 'main');
});

it('shows screen into main slot using showInto and emits navigate directive', function () {
    $user = User::factory()->create();
    /** @var TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    $shown = $host->showInto(TableDemo::class);
    expect($shown)->toBeTrue();

    $collector = app(UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
});

it('supports show method using defaultSlot', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    expect(TableDemo::getDefaultSlot())->toBe('main');

    $shown = $host->show(TableDemo::class);
    expect($shown)->toBeTrue();

    $collector = app(UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
});

it('supports navigate method delegating to layout and tracks active screen', function () {
    $user = User::factory()->create();
    /** @var TestCase $this */
    $this->actingAs($user);

    $layout = new class extends AbstractLayout
    {
        public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void
        {
            $topMenu = UI::container('top_menu');
            $this->registerSlot('top_menu', $topMenu);

            $main = UI::container('content_container');
            $this->registerSlot('main', $main);

            $root->add($topMenu)->add($main);
            $contentBuilder($main);
        }
    };

    AbstractLayout::setCurrent($layout);

    $root = UI::container('root');
    $layout->build($root, fn ($c) => null);

    $host = app(Menu::class);
    $host->initializeEventContext();

    expect($host->getLayout())->not->toBeNull();

    $navigated = $host->navigate(TableDemo::class);
    expect($navigated)->toBeTrue();

    expect($layout->getActiveScreen('main'))->toBe(TableDemo::class);

    $collector = app(UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
    expect($allChanges['navigate']['slot'])->toBe('content_container');
});

it('returns early when target screen is already active in the slot', function () {
    $user = User::factory()->create();
    /** @var TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    $shownFirst = $host->showInto(TableDemo::class);
    expect($shownFirst)->toBeTrue();

    $collector = app(UIChangesCollector::class);
    expect($collector->all())->toHaveKey('navigate');

    // Reset collector to test subsequent call
    $collector->reset();

    // Calling showInto again with the same screen already active in the slot
    $shownSecond = $host->showInto(TableDemo::class);
    expect($shownSecond)->toBeTrue();
    expect($collector->all())->not->toHaveKey('navigate');

    // Calling showInto with force: true reloads despite active screen
    $collector->reset();
    $shownThird = $host->showInto(TableDemo::class, force: true);
    expect($shownThird)->toBeTrue();
    expect($collector->all())->toHaveKey('navigate');
});

class TestForceReloadCounterScreen extends Screen
{
    public const ROUTE_PATH = '/test-force-reload';

    public static int $buildCount = 0;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        self::$buildCount++;
        $container->add(UI::button('btn_test'));
    }
}

it('clears cached screen snapshot and rebuilds UI when force is true in showInto', function () {
    $user = User::factory()->create();
    /** @var TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    TestForceReloadCounterScreen::$buildCount = 0;

    // First load builds the UI from scratch
    $host->showInto(TestForceReloadCounterScreen::class);
    expect(TestForceReloadCounterScreen::$buildCount)->toBe(1);

    // Switch to another screen so the slot is no longer TestForceReloadCounterScreen
    $host->showInto(TableDemo::class);

    // Navigating back without force reuses cached snapshot (buildBaseUI is not re-invoked)
    $host->showInto(TestForceReloadCounterScreen::class, force: false);
    expect(TestForceReloadCounterScreen::$buildCount)->toBe(1);

    // Switch away again
    $host->showInto(TableDemo::class);

    // Navigating with force: true clears the cached snapshot and re-invokes buildBaseUI
    $host->showInto(TestForceReloadCounterScreen::class, force: true);
    expect(TestForceReloadCounterScreen::$buildCount)->toBe(2);
});

class TestParamsReceiverScreen extends Screen
{
    public const ROUTE_PATH = '/test-params-receiver';

    public static array $receivedParams = [];

    public static int $buildCount = 0;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        self::$buildCount++;
        self::$receivedParams = $params;
        $label = (string) ($params['post_id'] ?? 'default');
        $container->add(UI::button('btn_param_'.$label)->label($label));
    }
}

it('propagates params through showInto down to target buildBaseUI', function () {
    $user = User::factory()->create();
    /** @var TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    TestParamsReceiverScreen::$receivedParams = [];
    TestParamsReceiverScreen::$buildCount = 0;

    $host->showInto(TestParamsReceiverScreen::class, params: ['post_id' => 42, 'filter' => 'published']);

    expect(TestParamsReceiverScreen::$buildCount)->toBe(1);
    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => 42, 'filter' => 'published']);

    $collector = app(UIChangesCollector::class);
    $allChanges = $collector->all();
    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TestParamsReceiverScreen::getRoutePath().'?post_id=42&filter=published');

    // Navigating again with new parameters should reload with the new params even without explicit force
    $collector->reset();
    $host->showInto(TestParamsReceiverScreen::class, params: ['post_id' => 99]);

    expect(TestParamsReceiverScreen::$buildCount)->toBe(2);
    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => 99]);
    expect($collector->all()['navigate']['url'])->toBe(TestParamsReceiverScreen::getRoutePath().'?post_id=99');
});

it('propagates params through Screen::embedInto and Container::embed', function () {
    TestParamsReceiverScreen::$receivedParams = [];
    TestParamsReceiverScreen::$buildCount = 0;

    $parent = UI::container('test_parent');
    Screen::embedInto(TestParamsReceiverScreen::class, $parent, ['post_id' => 101]);

    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => 101]);
    expect(TestParamsReceiverScreen::$buildCount)->toBe(1);

    $parent2 = UI::container('test_parent_2');
    $parent2->embed(TestParamsReceiverScreen::class, ['post_id' => 202]);

    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => 202]);
    expect(TestParamsReceiverScreen::$buildCount)->toBe(2);
});

it('propagates params in HandlesNavigation fallback container tree', function () {
    $hostScreen = new class extends Screen
    {
        public const ROUTE_PATH = '/test-host-screen';

        public static ?string $layout = null;

        public static function authorize(): bool
        {
            return true;
        }

        protected function buildBaseUI(Container $container, ...$params): void
        {
            $container->add(UI::container('custom_slot'));
        }
    };

    $hostScreen->initializeEventContext();
    TestParamsReceiverScreen::$receivedParams = [];

    $hostScreen->showInto(TestParamsReceiverScreen::class, slot: 'custom_slot', params: ['post_id' => 303]);

    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => 303]);

    $collector = app(UIChangesCollector::class);
    $collector->reset();
    $hostScreen->showInto(TestParamsReceiverScreen::class, slot: 'main', params: ['post_id' => 404]);
    expect($collector->all()['navigate']['url'] ?? null)->toBe(TestParamsReceiverScreen::getRoutePath().'?post_id=404');
});

it('passes URL query params as buildParams to buildBaseUI in UIController::show', function () {
    /** @var TestCase $this */
    config()->set('usim.screens_namespace', '');
    TestParamsReceiverScreen::$receivedParams = [];
    TestParamsReceiverScreen::$buildCount = 0;

    $response = $this->getJson('/api/ui/test-params-receiver-screen?post_id=55&mode=edit&reset=true&parent=main');

    $response->assertOk();
    expect(TestParamsReceiverScreen::$buildCount)->toBe(1);
    expect(TestParamsReceiverScreen::$receivedParams)->toBe(['post_id' => '55', 'mode' => 'edit']);
});
