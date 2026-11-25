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
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;

it('navigates via redirect using direct Screen and custom expectations', function () {
    /** @var Login $screen */
    $screen = Screen::make(Login::class);
    $screen->navigate_forgot_password();

    expect($screen->getUiChanges()->hasRedirect('/auth/forgot-password'))->toBeTrue();
    expect($screen)->toContainRedirect('/auth/forgot-password');
});

it('submits login and updates UI on success using direct Screen and custom expectations', function () {
    $loginAction = Mockery::mock(LoginActionInterface::class);
    $loginAction->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function () {
            event(new UsimEvent('logged_user', [
                'token' => 'mock-jwt-token',
                'message' => 'Login successful',
            ]));

            return AuthResult::success(
                redirectTo: '/home',
                token: 'mock-jwt-token',
                message: 'Login successful'
            );
        });

    app()->instance(LoginActionInterface::class, $loginAction);

    /** @var Login $screen */
    $screen = Screen::make(Login::class);
    $screen->render();
    $screen->submit_login([
        'login_email' => 'admin@test.com',
        'login_password' => 'secret',
    ]);

    expect($screen->getUiChanges())->toContainToast('Login successful', 'success');
    expect($screen)->toContainToast('Login successful', 'success');
    expect($screen->getUiChanges()->hasRedirect())->toBeFalse();
});

it('handles login failure and produces error toast using ScreenTestHarness', function () {
    $loginAction = Mockery::mock(LoginActionInterface::class);
    $loginAction->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function () {
            event(new UsimEvent('login_failed', [
                'message' => 'Invalid credentials',
            ]));

            return AuthResult::failed(AuthStatus::BAD_CREDENTIALS, 'Invalid credentials');
        });

    app()->instance(LoginActionInterface::class, $loginAction);

    $harness = ScreenTestHarness::for(Login::class);
    $harness->render();
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

it('provides CardGrid and ItemCard inspection, assertion, and click helpers in ScreenTestHarness and Container::findByName', function () {
    $screen = new class extends Screen
    {
        protected \Idei\Usim\Components\CardGrid $posts_grid;

        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void
        {
            $this->posts_grid = \Idei\Usim\UI::cardGrid('posts_grid')
                ->onCardClick('open_post')
                ->items([
                    \Idei\Usim\DTOs\CardItemDTO::make(
                        id: 42,
                        title: 'Primer Post',
                        badge: '🟡 En revisión',
                        badgeStyle: \Idei\Usim\Enums\Style::WARNING,
                        actions: [
                            \Idei\Usim\DTOs\CardActionDTO::make(
                                label: 'Aprobar',
                                action: 'approve_post',
                                parameters: ['source' => 'card'],
                                style: \Idei\Usim\Enums\Style::SUCCESS,
                            ),
                            \Idei\Usim\DTOs\CardActionDTO::make(
                                label: '',
                                action: 'delete_post',
                                icon: 'trash',
                                style: \Idei\Usim\Enums\Style::DANGER,
                                tooltip: 'Eliminar post',
                            ),
                        ],
                    ),
                    \Idei\Usim\DTOs\CardItemDTO::make(
                        id: 99,
                        title: 'Segundo Post',
                        badge: '🟢 Publicado',
                        badgeStyle: \Idei\Usim\Enums\Style::SUCCESS,
                    ),
                ]);

            $container->add($this->posts_grid);
        }

        /**
         * @param  array<string, mixed>  $params
         */
        public function onApprovePost(array $params = []): void
        {
            $id = (int) ($params['id'] ?? 0);
            $source = (string) ($params['source'] ?? '');
            $this->posts_grid->patchCard($id, [
                'badge' => '🟢 Publicado',
                'badgeStyle' => \Idei\Usim\Enums\Style::SUCCESS,
            ]);
            $this->toast("Post {$id} aprobado ({$source})", 'success');
        }

        /**
         * @param  array<string, mixed>  $params
         */
        public function onDeletePost(array $params = []): void
        {
            $id = (int) ($params['id'] ?? 0);
            $this->posts_grid->removeCard($id);
            $this->toast("Post {$id} eliminado", 'info');
        }

        /**
         * @param  array<string, mixed>  $params
         */
        public function onOpenPost(array $params = []): void
        {
            $id = (int) ($params['id'] ?? 0);
            $this->redirect("/posts/{$id}");
        }
    };

    $harness = ScreenTestHarness::for($screen)->render();

    // Container::findByName() & findById() locate mounted child ItemCards inside CardGrid
    $cardByName = $harness->findComponent('posts_grid__card_42');
    expect($cardByName)->toBeInstanceOf(\Idei\Usim\Components\ItemCard::class);
    assert($cardByName instanceof \Idei\Usim\Components\ItemCard);
    expect($harness->getContainer()?->findById($cardByName->getId()))->toBe($cardByName);

    // ScreenTestHarness CardGrid / ItemCard assertions
    $harness
        ->assertCardCount('posts_grid', 2)
        ->assertHasCard('posts_grid', 42)
        ->assertMissingCard('posts_grid', 777)
        ->assertCardBadge('posts_grid', 42, '🟡 En revisión', \Idei\Usim\Enums\Style::WARNING)
        ->assertCardHasAction('posts_grid', 42, 'approve_post')
        ->assertCardHasAction('posts_grid', 42, 'delete_post')
        ->assertCardMissingAction('posts_grid', 99, 'delete_post');

    expect($harness->findCard('posts_grid', 42))->toBe($cardByName)
        ->and($harness->findCard('posts_grid', 777))->toBeNull();

    // Clicking a card action merges configured parameters + item id automatically
    $harness->clickCardAction('posts_grid', 42, 'approve_post')
        ->assertToast('Post 42 aprobado (card)', 'success')
        ->assertCardBadge('posts_grid', 42, '🟢 Publicado', \Idei\Usim\Enums\Style::SUCCESS);

    // Clicking the card body invokes click_action with default id
    $harness->clickCard('posts_grid', 99)
        ->assertRedirect('/posts/99');

    // Clicking delete_post removes the card from grid & static_items
    $harness->clickCardAction('posts_grid', 42, 'delete_post')
        ->assertToast('Post 42 eliminado', 'info')
        ->assertMissingCard('posts_grid', 42)
        ->assertCardCount('posts_grid', 1);
});
