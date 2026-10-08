<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Contracts\ScreenAuthorizerInterface;

it('authorizes screen action using a mock ScreenAuthorizerInterface', function () {
    $authorizer = Mockery::mock(ScreenAuthorizerInterface::class);

    $authorizer->shouldReceive('can')
        ->once()
        ->with(Mockery::any(), 'admin.users_manager.edit_user', 'main')
        ->andReturn(true);

    $authorizer->shouldReceive('hasRole')
        ->once()
        ->with(Mockery::any(), ['admin'], 'main')
        ->andReturn(true);

    app()->instance(ScreenAuthorizerInterface::class, $authorizer);

    // Mock an authenticated user
    $user = new User(['name' => 'Mock User', 'email' => 'mock@example.com']);
    $this->actingAs($user);

    $screen = app(UsersManager::class);

    expect($screen->userCan('edit_user', 'main'))->toBeTrue();
    expect($screen->userHasRole(['admin'], 'main'))->toBeTrue();
});

it('denies screen action when mock ScreenAuthorizerInterface returns false', function () {
    $authorizer = Mockery::mock(ScreenAuthorizerInterface::class);

    $authorizer->shouldReceive('can')
        ->once()
        ->with(Mockery::any(), 'admin.users_manager.delete_user', 'main')
        ->andReturn(false);

    app()->instance(ScreenAuthorizerInterface::class, $authorizer);

    $user = new User(['name' => 'Mock User', 'email' => 'mock@example.com']);
    $this->actingAs($user);

    $screen = app(UsersManager::class);

    expect($screen->userCan('delete_user', 'main'))->toBeFalse();
});
