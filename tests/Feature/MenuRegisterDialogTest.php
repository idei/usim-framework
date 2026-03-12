<?php

use App\Models\User;
use App\UI\Screens\Auth\EmailVerified;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Notifications\CustomVerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

it('opens register modal from guest menu', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    $response = $ui->action('user_menu', 'show_register_form');
    $response->assertOk();

    $payload = $response->json();

    expect(hasModalComponents($payload))->toBeTrue();
    expect(modalPayloadHasNamedComponent($payload, 'register_dialog'))->toBeTrue();

    $ui->component('name')->expect('type')->toBe('input');
    $ui->component('email')->expect('type')->toBe('input');
    $ui->component('password')->expect('type')->toBe('input');
    $ui->component('password_confirmation')->expect('type')->toBe('input');
    $ui->component('btn_submit_register')->expect('action')->toBe('submit_register');
    $ui->assertNoIssues();
});

it('submits register modal and sends verification notification', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();
    Role::findOrCreate('user');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    $openResponse = $ui->action('user_menu', 'show_register_form');
    $openResponse->assertOk();

    $email = 'register.modal@example.com';
    $submitButton = $ui->component('btn_submit_register')->data();
    $baseParameters = is_array($submitButton['parameters'] ?? null)
        ? $submitButton['parameters']
        : [];

    $submitResponse = $ui->click('btn_submit_register', array_merge($baseParameters, [
        'name' => 'Register Modal User',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'accept_terms' => true
    ]));

    $submitResponse->assertOk();
    expect($submitResponse->json('redirect'))->toBe(Home::getRoutePath());
    expect($submitResponse->json('toast.type'))->toBe('success');

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull();
    expect($user?->email_verified_at)->toBeNull();
    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, CustomVerifyEmailNotification::class);
    $this->assertDatabaseHas('users', ['email' => $email]);

    $ui->assertNoIssues();
});

it('verifies the registered user after opening the email verification link', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();
    Role::findOrCreate('user');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $ui->action('user_menu', 'show_register_form')->assertOk();

    $email = 'register.verify@example.com';
    $submitButton = $ui->component('btn_submit_register')->data();
    $baseParameters = is_array($submitButton['parameters'] ?? null)
        ? $submitButton['parameters']
        : [];

    $ui->click('btn_submit_register', array_merge($baseParameters, [
        'name' => 'Register Verify User',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'accept_terms' => true
    ]))->assertOk();

    /** @var User|null $user */
    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user?->email_verified_at)->toBeNull();

    $verificationUrl = null;

    Notification::assertSentTo(
        $user,
        CustomVerifyEmailNotification::class,
        function (CustomVerifyEmailNotification $notification) use ($user, &$verificationUrl): bool {
            $mailMessage = $notification->toMail($user);
            $url = $mailMessage->viewData['verificationUrl'] ?? null;

            if (!is_string($url) || $url === '') {
                return false;
            }

            $verificationUrl = $url;
            return true;
        }
    );

    expect($verificationUrl)->toStartWith('http');

    $parsedUrl = parse_url((string) $verificationUrl);
    parse_str($parsedUrl['query'] ?? '', $query);

    $verificationQuery = array_merge($query, ['reset' => true]);

    $verificationUi = uiScenario($this, EmailVerified::class, $verificationQuery);

    $verificationUi->component('verified_message')->expect('type')->toBe('label');
    expect((string) ($verificationUi->component('verified_message')->data()['text'] ?? ''))
        ->toContain('verificado satisfactoriamente');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();

    // Reusing the same verification link should show "already verified" state.
    $alreadyVerifiedUi = uiScenario($this, EmailVerified::class, $verificationQuery);

    $alreadyVerifiedUi->component('already_verified_message')->expect('type')->toBe('label');
    expect((string) ($alreadyVerifiedUi->component('already_verified_message')->data()['text'] ?? ''))
        ->toContain('ya ha sido verificado anteriormente');

    // Build a truly expired signed URL and assert expired-link UI state.
    $expiredUrl = URL::temporarySignedRoute('ui.catchall', now()->subMinute(), [
        'screen' => 'auth/email-verified',
        'id' => (int) ($query['id'] ?? 0),
        'hash' => (string) ($query['hash'] ?? ''),
    ]);

    $expiredParsedUrl = parse_url($expiredUrl);
    $expiredQuery = [];
    parse_str($expiredParsedUrl['query'] ?? '', $expiredQuery);

    $expiredLikeUi = uiScenario($this, EmailVerified::class, array_merge($expiredQuery, ['reset' => true]));

    $expiredLikeUi->component('error_message')->expect('type')->toBe('label');
    expect((string) ($expiredLikeUi->component('error_detail')->data()['text'] ?? ''))
        ->toContain('ha expirado');

    $verificationUi->assertNoIssues();
    $alreadyVerifiedUi->assertNoIssues();
    $expiredLikeUi->assertNoIssues();
});
