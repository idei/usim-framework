<?php

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\UI\Screens\Auth\ForgotPassword;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Auth\ResetPassword;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\Contracts\PasswordResetActionInterface;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\DTOs\AuthResult;
use Idei\Usim\DTOs\LoginCredentials;
use Idei\Usim\DTOs\PasswordResetResult;
use Idei\Usim\DTOs\RegisterData;
use Idei\Usim\DTOs\RegistrationResult;
use Idei\Usim\Enums\AuthStatus;
use Idei\Usim\Screen;

it('allows Login screen to be tested in memory with mocked LoginActionInterface on success', function () {
    $loginActionMock = Mockery::mock(LoginActionInterface::class);
    $loginActionMock->shouldReceive('execute')
        ->once()
        ->withArgs(function (LoginCredentials $credentials, bool $startSession) {
            return $credentials->email === 'admin@test.com'
                && $credentials->password === 'secret123'
                && $startSession === true;
        })
        ->andReturn(AuthResult::success(
            redirectTo: '/dashboard',
            token: 'mock-auth-token-123',
            message: 'Inicio de sesión exitoso'
        ));

    app()->instance(LoginActionInterface::class, $loginActionMock);

    /** @var Login $screen */
    $screen = Screen::make(Login::class);
    $screen->render();

    $screen->onSubmitLogin([
        'login_email' => 'admin@test.com',
        'login_password' => 'secret123',
        'remember' => false,
    ]);

    $changes = $screen->getUiChanges()->all();

    // Verify UI feedback toast
    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
    expect($changes['toast']['message'])->toBe('Inicio de sesión exitoso');
});

it('allows Login screen to handle failure in memory with mocked LoginActionInterface', function () {
    $loginActionMock = Mockery::mock(LoginActionInterface::class);
    $loginActionMock->shouldReceive('execute')
        ->once()
        ->andReturn(AuthResult::failed(
            status: AuthStatus::BAD_CREDENTIALS,
            message: 'Credenciales inválidas'
        ));

    app()->instance(LoginActionInterface::class, $loginActionMock);

    /** @var Login $screen */
    $screen = Screen::make(Login::class);
    $screen->render();

    $screen->onSubmitLogin([
        'login_email' => 'wrong@test.com',
        'login_password' => 'badpassword',
        'remember' => false,
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('error');
    expect($changes['toast']['message'])->toBe('Credenciales inválidas');
});

it('allows Register screen to be tested in memory with mocked RegisterActionInterface on success', function () {
    $mockUser = Mockery::mock(User::class);

    $registerActionMock = Mockery::mock(RegisterActionInterface::class);
    $registerActionMock->shouldReceive('execute')
        ->once()
        ->withArgs(function (RegisterData $data) {
            return $data->name === 'Juan Pérez'
                && $data->email === 'juan@test.com'
                && $data->password === 'password123';
        })
        ->andReturn(RegistrationResult::success(
            message: 'Registro exitoso',
            user: $mockUser,
            token: 'mock-reg-token-456'
        ));

    $authSessionMock = Mockery::mock(AuthSessionService::class);
    $authSessionMock->shouldReceive('establishSession')
        ->once()
        ->with($mockUser, null, 'mock-reg-token-456');

    app()->instance(RegisterActionInterface::class, $registerActionMock);
    app()->instance(AuthSessionService::class, $authSessionMock);

    /** @var Register $screen */
    $screen = Screen::make(Register::class);
    $screen->render();

    $screen->onSubmitRegister([
        'name' => 'Juan Pérez',
        'email' => 'juan@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'accept_terms' => true,
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
    expect($changes['toast']['message'])->toBe('Registro exitoso');
});

it('allows Register screen to handle validation errors in memory', function () {
    $registerActionMock = Mockery::mock(RegisterActionInterface::class);
    $registerActionMock->shouldReceive('execute')
        ->once()
        ->andReturn(RegistrationResult::failed(
            message: 'El correo ya existe',
            errors: ['email' => ['El correo ya existe en el sistema']]
        ));

    app()->instance(RegisterActionInterface::class, $registerActionMock);

    /** @var Register $screen */
    $screen = Screen::make(Register::class);
    $screen->render();

    $screen->onSubmitRegister([
        'name' => 'Juan Duplicado',
        'email' => 'duplicado@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'accept_terms' => true,
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('error');
    expect($changes['toast']['message'])->toBe('El correo ya existe en el sistema');
});

it('allows ForgotPassword screen to be tested in memory with mocked PasswordResetActionInterface', function () {
    $actionMock = Mockery::mock(PasswordResetActionInterface::class);
    $actionMock->shouldReceive('sendResetLink')
        ->once()
        ->with('user@example.com')
        ->andReturn(PasswordResetResult::success('Enlace de recuperación enviado'));

    app()->instance(PasswordResetActionInterface::class, $actionMock);

    /** @var ForgotPassword $screen */
    $screen = Screen::make(ForgotPassword::class);
    $screen->render();

    $screen->onSendLink([
        'email' => 'user@example.com',
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
});

it('allows ResetPassword screen to be tested in memory with mocked PasswordResetActionInterface', function () {
    $actionMock = Mockery::mock(PasswordResetActionInterface::class);
    $actionMock->shouldReceive('resetPassword')
        ->once()
        ->with('valid-token', 'user@example.com', 'newPassword123', 'newPassword123')
        ->andReturn(PasswordResetResult::success('Contraseña restablecida exitosamente'));

    app()->instance(PasswordResetActionInterface::class, $actionMock);

    /** @var ResetPassword $screen */
    $screen = Screen::make(ResetPassword::class);
    $screen->render();

    $screen->onResetPassword([
        'token' => 'valid-token',
        'email' => 'user@example.com',
        'password' => 'newPassword123',
        'password_confirmation' => 'newPassword123',
    ]);

    $changes = $screen->getUiChanges()->all();

    expect($changes)->toHaveKey('toast');
    expect($changes['toast']['type'])->toBe('success');
    expect($changes)->toHaveKey('redirect');
    expect($changes['redirect'])->toBe('/auth/login');
});
