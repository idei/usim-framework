<?php

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\ScreenLifecycleOrchestrator;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Mockery\MockInterface;
use Tests\Support\LifecycleTestScreen;

afterEach(function () {
    Mockery::close();
});

it('implements ScreenLifecycleOrchestratorInterface', function () {
    /** @var UIStateRepositoryInterface&MockInterface $stateRepo */
    $stateRepo = Mockery::mock(UIStateRepositoryInterface::class);
    /** @var UIDifferInterface&MockInterface $differ */
    $differ = Mockery::mock(UIDifferInterface::class);
    /** @var ComponentIdGeneratorInterface&MockInterface $idGenerator */
    $idGenerator = Mockery::mock(ComponentIdGeneratorInterface::class);
    $collector = new UIChangesCollector;

    $orchestrator = new ScreenLifecycleOrchestrator(
        stateRepository: $stateRepo,
        differ: $differ,
        idGenerator: $idGenerator,
        changesCollector: $collector
    );

    expect($orchestrator)->toBeInstanceOf(ScreenLifecycleOrchestratorInterface::class);
});

it('resolves ScreenLifecycleOrchestratorInterface from container', function () {
    $orchestrator = app(ScreenLifecycleOrchestratorInterface::class);
    expect($orchestrator)->toBeInstanceOf(ScreenLifecycleOrchestrator::class);
});

it('orchestrates render lifecycle by building container, diffing and collecting changes', function () {
    /** @var UIStateRepositoryInterface&MockInterface $stateRepo */
    $stateRepo = Mockery::mock(UIStateRepositoryInterface::class);
    /** @var UIDifferInterface&MockInterface $differ */
    $differ = Mockery::mock(UIDifferInterface::class);
    /** @var ComponentIdGeneratorInterface&MockInterface $idGenerator */
    $idGenerator = Mockery::mock(ComponentIdGeneratorInterface::class);
    $collector = new UIChangesCollector;

    $orchestrator = new ScreenLifecycleOrchestrator(
        stateRepository: $stateRepo,
        differ: $differ,
        idGenerator: $idGenerator,
        changesCollector: $collector
    );
    app()->instance(ScreenLifecycleOrchestratorInterface::class, $orchestrator);

    $screen = new LifecycleTestScreen;
    $contextKey = $screen->getContextIdentifier();

    $idGenerator->shouldReceive('pushCurrentContext')->once()->with($contextKey);
    $idGenerator->shouldReceive('popCurrentContext')->once();
    $idGenerator->shouldReceive('reserveContextId');

    // Cache miss: returns null, then stores snapshot
    $stateRepo->shouldReceive('get')->once()->with($contextKey)->andReturnNull();
    $stateRepo->shouldReceive('clear')->never();
    $stateRepo->shouldReceive('store')->atLeast()->once();
    $stateRepo->shouldReceive('storeScreenState')->atLeast()->once();
    $stateRepo->shouldReceive('getScreenState')->once()->with($contextKey)->andReturn([]);

    $differ->shouldReceive('compare')->once()->andReturn([
        1 => ['type' => 'container'],
    ]);

    $orchestrator->render($screen);

    expect($screen->hasContainer())->toBeTrue();
    $container = $screen->getContainer();
    expect($container)->not->toBeNull();
    expect($container?->getName())->toContain('lifecycletestscreen');
    expect($collector->getStorage())->toBeArray();

    $entries = $collector->all();
    expect($entries)->toHaveKey(1);
    expect($entries)->toHaveKey('agent_context');
    expect($entries['agent_context'])->toBe(['unit_test' => true]);
});

it('orchestrates handleAction lifecycle and dispatches to screen action handler', function () {
    /** @var UIStateRepositoryInterface&MockInterface $stateRepo */
    $stateRepo = Mockery::mock(UIStateRepositoryInterface::class);
    /** @var UIDifferInterface&MockInterface $differ */
    $differ = Mockery::mock(UIDifferInterface::class);
    /** @var ComponentIdGeneratorInterface&MockInterface $idGenerator */
    $idGenerator = Mockery::mock(ComponentIdGeneratorInterface::class);
    $collector = new UIChangesCollector;

    $orchestrator = new ScreenLifecycleOrchestrator(
        stateRepository: $stateRepo,
        differ: $differ,
        idGenerator: $idGenerator,
        changesCollector: $collector
    );
    app()->instance(ScreenLifecycleOrchestratorInterface::class, $orchestrator);

    $screen = new LifecycleTestScreen;
    $contextKey = $screen->getContextIdentifier();

    $idGenerator->shouldReceive('pushCurrentContext')->once()->with($contextKey);
    $idGenerator->shouldReceive('popCurrentContext')->once();
    $idGenerator->shouldReceive('reserveContextId');

    $stateRepo->shouldReceive('getScreenState')->once()->with($contextKey)->andReturn(['state_counter' => 50]);
    $stateRepo->shouldReceive('get')->once()->with($contextKey)->andReturnNull();
    $stateRepo->shouldReceive('store')->atLeast()->once();
    $stateRepo->shouldReceive('storeScreenState')->atLeast()->once();

    $differ->shouldReceive('compare')->once()->andReturn([]);

    $orchestrator->handleAction(
        screen: $screen,
        method: 'onIncrement',
        parameters: ['step' => 5]
    );

    expect($screen->actionExecuted)->toBeTrue();
    expect($screen->receivedActionParams)->toBe(['step' => 5]);
    expect($screen->getStateVariables()['state_counter'])->toBe(55);
});

it('clears cached snapshot through clearCachedScreenSnapshot', function () {
    /** @var UIStateRepositoryInterface&MockInterface $stateRepo */
    $stateRepo = Mockery::mock(UIStateRepositoryInterface::class);
    /** @var UIDifferInterface&MockInterface $differ */
    $differ = Mockery::mock(UIDifferInterface::class);
    /** @var ComponentIdGeneratorInterface&MockInterface $idGenerator */
    $idGenerator = Mockery::mock(ComponentIdGeneratorInterface::class);
    $collector = new UIChangesCollector;

    $orchestrator = new ScreenLifecycleOrchestrator(
        stateRepository: $stateRepo,
        differ: $differ,
        idGenerator: $idGenerator,
        changesCollector: $collector
    );

    $screen = new LifecycleTestScreen;
    $contextKey = $screen->getContextIdentifier();

    $stateRepo->shouldReceive('clearScreenState')->once()->with($contextKey)->andReturn(true);
    $stateRepo->shouldReceive('clear')->once()->with($contextKey)->andReturn(true);

    $result = $orchestrator->clearCachedScreenSnapshot($screen);
    expect($result)->toBeTrue();
});

it('preserves transformed values in buildDiffResponse when dirty component properties exist', function () {
    $orchestrator = app(ScreenLifecycleOrchestratorInterface::class);

    $screen = new class extends Screen
    {
        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    $container = UI::container('test_screen')->root(true);
    $img = UI::image('avatar')->src('avatar.png')->storage('uploads/avatars');
    $container->add($img);

    $screen->setContainer($container);
    $newUI = $container->toJson();
    $screen->setNewUI($newUI);
    $screen->setOldUI([]);

    $diff = $orchestrator->buildDiffResponse($screen);

    $imgId = $img->getId();
    expect($diff)->toHaveKey($imgId);
    expect($diff[$imgId]['src'])->toBe(url('/files/uploads/avatars/avatar.png'));
    expect($diff[$imgId]['src'])->not->toBe('avatar.png');
});

it('preserves transformed url when image source is mutated during reactive event lifecycle', function () {
    $orchestrator = app(ScreenLifecycleOrchestratorInterface::class);

    $screen = new class extends Screen
    {
        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    $container = UI::container('test_screen')->root(true);
    $img = UI::image('avatar')->src('avatar1.png')->storage('uploads/avatars');
    $container->add($img);

    $screen->setContainer($container);
    $oldUI = $container->toJson();
    $screen->setOldUI($oldUI);

    // Component is mutated dynamically during event handler
    $img->src('avatar2.png');
    $newUI = $container->toJson();
    $screen->setNewUI($newUI);

    $diff = $orchestrator->buildDiffResponse($screen);

    $imgId = $img->getId();
    expect($diff)->toHaveKey($imgId);
    expect($diff[$imgId]['src'])->toBe(url('/files/uploads/avatars/avatar2.png'));
    expect($diff[$imgId]['src'])->not->toBe('avatar2.png');
});
