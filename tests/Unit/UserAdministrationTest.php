<?php

use App\Models\User;
use App\UI\Screens\Admin\EditUser;
use App\UI\Screens\Admin\TableModels\UserTableModel;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\Contracts\UserListingServiceInterface;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\DTOs\RegisterData;
use Idei\Usim\DTOs\RegistrationResult;
use Idei\Usim\Screen;
use Idei\Usim\UI;

it('allows EditUser to update a user in memory using mocked UserMutationServiceInterface', function () {
    $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
    $user->id = 42;

    $mockMutationService = Mockery::mock(UserMutationServiceInterface::class);
    $mockMutationService->shouldReceive('findUser')
        ->with(42)
        ->once()
        ->andReturn($user);

    $mockMutationService->shouldReceive('updateUser')
        ->once()
        ->withArgs(function ($targetUser, array $data) use ($user) {
            return $targetUser === $user
                && $data['name'] === 'Jane Doe';
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Usuario actualizado correctamente',
        ]);

    app()->instance(UserMutationServiceInterface::class, $mockMutationService);

    /** @var EditUser $screen */
    $screen = Screen::make(EditUser::class);
    $screen->render(buildParams: [
        'user' => [
            'id' => 42,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ],
    ]);

    $screen->onSubmitUpdateUser([
        'user_id' => 42,
        'name' => 'Jane Doe',
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
    expect($changes['toast']['message'])->toBe('Usuario actualizado correctamente');
});

it('allows EditUser to delete a user in memory using mocked UserMutationServiceInterface', function () {
    $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
    $user->id = 42;

    $mockMutationService = Mockery::mock(UserMutationServiceInterface::class);
    $mockMutationService->shouldReceive('findUser')
        ->with(42)
        ->once()
        ->andReturn($user);

    $mockMutationService->shouldReceive('deleteUser')
        ->with($user)
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'Usuario eliminado correctamente',
        ]);

    app()->instance(UserMutationServiceInterface::class, $mockMutationService);

    /** @var EditUser $screen */
    $screen = Screen::make(EditUser::class);
    $screen->render();

    $screen->onConfirmDeleteUser([
        'user_id' => 42,
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
});

it('allows UsersManager to submit registration in memory using RegisterActionInterface', function () {
    $mockRegisterAction = Mockery::mock(RegisterActionInterface::class);
    $mockRegisterAction->shouldReceive('execute')
        ->once()
        ->withArgs(function (RegisterData $data) {
            return $data->name === 'New User'
                && $data->email === 'newuser@example.com';
        })
        ->andReturn(RegistrationResult::success('Usuario registrado exitosamente'));

    app()->instance(RegisterActionInterface::class, $mockRegisterAction);

    /** @var UsersManager $screen */
    $screen = Screen::make(UsersManager::class);
    $screen->render();

    $screen->onSubmitRegister([
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
});

it('allows UserTableModel to resolve UserListingServiceInterface from container', function () {
    $mockListingService = Mockery::mock(UserListingServiceInterface::class);
    app()->instance(UserListingServiceInterface::class, $mockListingService);

    $table = UI::table('test_users_table');
    $model = new UserTableModel($table);

    expect($model->getListingService())->toBe($mockListingService);
});

