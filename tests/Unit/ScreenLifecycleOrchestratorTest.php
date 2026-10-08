<?php

use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Support\ScreenLifecycleOrchestrator;
use Idei\Usim\UIChangesCollector;
use Mockery;
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
