<?php

use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Support\UIStateManager;

it('delegates UIStateManager calls to mocked UIStateRepositoryInterface in memory', function () {
    $mockRepo = Mockery::mock(UIStateRepositoryInterface::class);

    $mockRepo->shouldReceive('getOrCreateClientId')
        ->once()
        ->andReturn('client-test-uuid-1234');

    $mockRepo->shouldReceive('storeScreenState')
        ->with('TestScreen', ['counter' => 42])
        ->once()
        ->andReturn(true);

    $mockRepo->shouldReceive('getScreenState')
        ->with('TestScreen')
        ->once()
        ->andReturn(['counter' => 42]);

    $mockRepo->shouldReceive('getClientActiveModalStack')
        ->with(null)
        ->once()
        ->andReturn([
            [
                'modal_class' => 'App\\UI\\Screens\\ConfirmModal',
                'caller_screen_id' => 10001,
                'caller_screen_class' => 'TestScreen',
                'callback_action' => 'onConfirm',
                'params' => ['id' => 5],
                'layer_index' => 0,
                'page_screen_route' => '/test',
            ],
        ]);

    app()->instance(UIStateRepositoryInterface::class, $mockRepo);

    expect(UIStateManager::getOrCreateClientId())->toBe('client-test-uuid-1234')
        ->and(UIStateManager::storeScreenState('TestScreen', ['counter' => 42]))->toBeTrue()
        ->and(UIStateManager::getScreenState('TestScreen'))->toBe(['counter' => 42]);

    $stack = UIStateManager::getClientActiveModalStack();
    expect($stack)->toHaveCount(1)
        ->and($stack[0]['modal_class'])->toBe('App\\UI\\Screens\\ConfirmModal');
});

