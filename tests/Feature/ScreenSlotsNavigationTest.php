<?php

use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Menu;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Idei\Usim\Components\Container;
use Idei\Usim\UI;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->seed([GenreSeeder::class, MovieSeeder::class]);
    \Idei\Usim\Layout\AbstractLayout::setCurrent(null);
    \Idei\Usim\Support\UIStateManager::setClientCurrentScreen('', null);
});

it('supports slot registration and retrieval on abstract layout', function () {
    /** @var \Tests\TestCase $this */
    $layout = new class extends \Idei\Usim\Layout\AbstractLayout {
        public function build(Container $root, \Closure $contentBuilder, ?string $menuScreen = null): void
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
    $layout->build($root, fn($c) => null);

    expect($layout->getSlot('sidebar'))->not->toBeNull();
    expect($layout->getSlot('sidebar')?->getName())->toBe('sidebar_slot');
    expect($layout->getSlot('main')?->getName())->toBe('main_slot');
    expect($layout->getSlot('non_existent'))->toBeNull();
    expect(array_keys($layout->getSlots()))->toContain('sidebar', 'main');
});

it('shows screen into main slot using showInto and emits navigate directive', function () {
    $user = \App\Models\User::factory()->create();
    /** @var \Tests\TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    $shown = $host->showInto(TableDemo::class);
    expect($shown)->toBeTrue();

    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
});

it('supports show method using defaultSlot', function () {
    /** @var \Tests\TestCase $this */
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    expect(TableDemo::getDefaultSlot())->toBe('main');

    $shown = $host->show(TableDemo::class);
    expect($shown)->toBeTrue();

    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
});

it('supports navigate method delegating to layout and tracks active screen', function () {
    $user = \App\Models\User::factory()->create();
    /** @var \Tests\TestCase $this */
    $this->actingAs($user);

    $layout = new class extends \Idei\Usim\Layout\AbstractLayout {
        public function build(Container $root, \Closure $contentBuilder, ?string $menuScreen = null): void
        {
            $topMenu = UI::container('top_menu');
            $this->registerSlot('top_menu', $topMenu);

            $main = UI::container('content_container');
            $this->registerSlot('main', $main);

            $root->add($topMenu)->add($main);
            $contentBuilder($main);
        }
    };

    \Idei\Usim\Layout\AbstractLayout::setCurrent($layout);

    $root = UI::container('root');
    $layout->build($root, fn($c) => null);

    $host = app(Menu::class);
    $host->initializeEventContext();

    expect($host->getLayout())->not->toBeNull();

    $navigated = $host->navigate(TableDemo::class);
    expect($navigated)->toBeTrue();

    expect($layout->getActiveScreen('main'))->toBe(TableDemo::class);

    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $allChanges = $collector->all();

    expect($allChanges)->toHaveKey('navigate');
    expect($allChanges['navigate']['url'])->toBe(TableDemo::getRoutePath());
    expect($allChanges['navigate']['slot'])->toBe('content_container');
});

it('returns early when target screen is already active in the slot', function () {
    $user = \App\Models\User::factory()->create();
    /** @var \Tests\TestCase $this */
    $this->actingAs($user);

    $host = app(Menu::class);
    $host->initializeEventContext();

    $shownFirst = $host->showInto(TableDemo::class);
    expect($shownFirst)->toBeTrue();

    $collector = app(\Idei\Usim\UIChangesCollector::class);
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

