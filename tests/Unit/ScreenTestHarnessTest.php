<?php

use App\Models\User;
use App\UI\Screens\Admin\EditUser;
use App\UI\Screens\Auth\ForgotPassword;
use App\UI\Screens\Auth\LegalTerms;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\Contracts\PasswordResetActionInterface;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\DTOs\AuthResult;
use Idei\Usim\DTOs\PasswordResetResult;
use Idei\Usim\Enums\AuthStatus;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;

it('submits login and navigates on success using direct Screen and custom expectations', function () {
    $loginAction = Mockery::mock(LoginActionInterface::class);
    $loginAction->shouldReceive('execute')
        ->once()
        ->andReturn(AuthResult::success('/dashboard'));

    app()->instance(LoginActionInterface::class, $loginAction);

    /** @var Login $screen */
    $screen = Screen::make(Login::class);
    $screen->submit_login([
        'login_email' => 'admin@test.com',
        'login_password' => 'secret',
    ]);

    expect($screen->getUiChanges())->toContainRedirect('/dashboard');
    expect($screen)->toContainRedirect('/dashboard');
});

it('handles login failure and produces error toast using ScreenTestHarness', function () {
    $loginAction = Mockery::mock(LoginActionInterface::class);
    $loginAction->shouldReceive('execute')
        ->once()
        ->andReturn(AuthResult::failed(AuthStatus::BAD_CREDENTIALS, 'Invalid credentials'));

    app()->instance(LoginActionInterface::class, $loginAction);

    $harness = ScreenTestHarness::for(Login::class);
    $harness->call('submit_login', [
        'login_email' => 'admin@test.com',
        'login_password' => 'wrong',
    ]);

    $harness->assertNoRedirect()
        ->assertToast('Invalid credentials', 'error');

    expect($harness->getChanges())->toContainToast('Invalid credentials', 'error');
    expect($harness)->toContainToast('Invalid credentials', 'error');
});

it('tests ForgotPassword in memory using testScreen helper and assertions', function () {
    $pwdAction = Mockery::mock(PasswordResetActionInterface::class);
    $pwdAction->shouldReceive('sendResetLink')
        ->once()
        ->with('test@example.com')
        ->andReturn(PasswordResetResult::success('Reset link sent'));

    app()->instance(PasswordResetActionInterface::class, $pwdAction);

    $harness = testScreen(ForgotPassword::class);
    $harness->render();
    $harness->assertHasComponent('email');

    $harness->send_link(['email' => 'test@example.com']);
    $harness->assertToast('Link sent', 'success');
    expect($harness)->toContainToast('Link sent', 'success');

    // Navigate to login
    $harness->navigate_to_login();
    $harness->assertRedirect('/auth/login');
    expect($harness)->toContainRedirect('/auth/login');
});

it('inspects component values and text in memory without database', function () {
    $harness = testScreen(ForgotPassword::class);
    $harness->render();

    $harness->assertHasComponent('email')
        ->assertHasComponent('btn_send')
        ->assertHasComponent('btn_back');

    expect($harness)->toHaveComponent('email');
    expect($harness)->toHaveComponent('btn_send');
});

it('handles validation failure on Login submission', function () {
    $harness = testScreen(Login::class);
    $harness->call('submit_login', [
        'login_email' => 'invalid-email',
        // missing password
    ]);

    $harness->assertNoRedirect()
        ->assertToast(type: 'error');

    expect($harness)->toContainToast(type: 'error');
});

it('tests EditUser submission in memory using ScreenTestHarness and UserMutationServiceInterface mock', function () {
    $user = new User([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $user->id = 15;

    $mutationService = Mockery::mock(UserMutationServiceInterface::class);
    $mutationService->shouldReceive('findUser')
        ->once()
        ->with(15)
        ->andReturn($user);

    $mutationService->shouldReceive('updateUser')
        ->once()
        ->with($user, Mockery::type('array'))
        ->andReturn([
            'status' => 'success',
            'message' => 'User updated successfully',
        ]);

    app()->instance(UserMutationServiceInterface::class, $mutationService);

    $harness = testScreen(EditUser::class);
    $harness->submit_update_user([
        'user_id' => 15,
        'name' => 'Jane Updated',
    ]);

    $harness->assertToast('User updated successfully', 'success');
    expect($harness)->toContainToast('User updated successfully', 'success');
});

it('persists and asserts incoming storage values in harness', function () {
    $harness = testScreen(Login::class, storage: ['theme' => 'dark', 'lang' => 'es']);

    $harness->assertStorageHas('theme', 'dark')
        ->assertStorageHas('lang', 'es');

    $harness->withStorage(['font_size' => 'large']);
    $harness->assertStorageHas('font_size', 'large');
});

it('asserts modal opening and closing', function () {
    $harness = testScreen(Register::class);
    $harness->open_terms_and_conditions();

    $harness->assertModal(LegalTerms::class);
    expect($harness)->toContainModal(LegalTerms::class);

    $modalScreen = Screen::make(LegalTerms::class);
    $modalScreen->setParent('modal');
    $modalHarness = testScreen($modalScreen);
    $modalHarness->call('close_legal_terms');

    $modalHarness->assertModalClosed();
    expect($modalHarness)->toContainModalClosed();
});
