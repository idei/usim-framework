<?php

use App\Models\User;
use App\UI\Screens\Auth\ForgotPassword;
use App\UI\Screens\Auth\ResetPassword;
use Idei\Usim\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('sends reset-password email from forgot-password screen', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'forgot.ui@example.com',
    ]);

    $ui = uiScenario($this, ForgotPassword::class, ['reset' => true]);

    $response = $ui->click('btn_send', [
        'email' => $user->email,
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    $ui->assertNoIssues();
});

it('resets password from the link received by email after validations pass', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset.ui@example.com',
        'password' => bcrypt('old-password-123'),
    ]);

    uiScenario($this, ForgotPassword::class, ['reset' => true])
        ->click('btn_send', ['email' => $user->email])
        ->assertOk();

    $resetUrl = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($user, &$resetUrl): bool {
            $mailMessage = $notification->toMail($user);
            $url = $mailMessage->viewData['resetUrl'] ?? null;

            if (!is_string($url) || $url === '') {
                return false;
            }

            $resetUrl = $url;
            return true;
        }
    );

    expect($resetUrl)->toStartWith('http');

    $parsedUrl = parse_url((string) $resetUrl);
    $query = [];
    parse_str($parsedUrl['query'] ?? '', $query);

    $resetUi = uiScenario($this, ResetPassword::class, array_merge($query, ['reset' => true]));

    $mismatchResponse = $resetUi->click('btn_reset', array_merge($query, [
        'reset_token' => (string) ($query['token'] ?? ''),
        'reset_email' => (string) ($query['email'] ?? ''),
        'password' => 'new-password-123',
        'password_confirmation' => 'different-password',
    ]));

    $mismatchResponse->assertOk();
    expect($mismatchResponse->json('toast.type'))->toBe('error');

    $validResponse = $resetUi->click('btn_reset', array_merge($query, [
        'reset_token' => (string) ($query['token'] ?? ''),
        'reset_email' => (string) ($query['email'] ?? ''),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]));

    $validResponse->assertOk();
    expect($validResponse->json('toast.type'))->toBe('success');
    expect($validResponse->json('redirect'))->toBe('/auth/login');

    $user->refresh();
    expect(Hash::check('new-password-123', (string) $user->password))->toBeTrue();
    expect(Hash::check('old-password-123', (string) $user->password))->toBeFalse();

    $resetUi->assertNoIssues();
});

it('rejects expired reset links in reset-password screen', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'expired.reset@example.com',
    ]);

    $expiredUrl = URL::temporarySignedRoute('ui.catchall', now()->subMinute(), [
        'screen' => 'auth/reset-password',
        'token' => 'expired-token',
        'email' => $user->email,
    ]);

    $parsedUrl = parse_url($expiredUrl);
    $query = [];
    parse_str($parsedUrl['query'] ?? '', $query);

    $resetUi = uiScenario($this, ResetPassword::class, array_merge($query, ['reset' => true]));

    $response = $resetUi->click('btn_reset', array_merge($query, [
        'reset_token' => (string) ($query['token'] ?? ''),
        'reset_email' => (string) ($query['email'] ?? ''),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]));

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('error');

    $result = $resetUi->component('lbl_result')->data();
    expect((string) ($result['text'] ?? ''))->toContain('expired');

    $user->refresh();
    expect(Hash::check('new-password-123', (string) $user->password))->toBeFalse();

    $resetUi->assertNoIssues();
});
