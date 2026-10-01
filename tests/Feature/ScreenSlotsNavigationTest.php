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
