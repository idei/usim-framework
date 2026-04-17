<?php

use App\UI\Screens\Auth\EmailVerified;
use Idei\Usim\Services\Support\UIStateManager;
use Illuminate\Support\Str;

it('persists the post-load snapshot after reset reloads', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        /** @var \Tests\TestCase $test */
        $test = $this;
        $clientId = (string) Str::uuid();

        $resetResponse = $test
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $clientId)
            ->getJson(screenApiUrl(EmailVerified::class, ['reset' => true]));

        $resetResponse->assertOk();
        expect(findComponentByName($resetResponse->json(), 'loading_message'))->toBeNull();
        expect(findComponentByName($resetResponse->json(), 'error_message')['text'] ?? null)
            ->toBe(t('screen.auth.email_verified.error.message'));
        expect(findComponentByName($resetResponse->json(), 'error_detail')['text'] ?? null)
            ->toBe(t('screen.auth.email_verified.errors.invalid_params'));

        $cachedResponse = $test
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $clientId)
            ->getJson(screenApiUrl(EmailVerified::class));

        $cachedResponse->assertOk();
        expect(findComponentByName($cachedResponse->json(), 'loading_message'))->toBeNull();
        expect(findComponentByName($cachedResponse->json(), 'error_message')['text'] ?? null)
            ->toBe(t('screen.auth.email_verified.error.message'));
        expect(findComponentByName($cachedResponse->json(), 'error_detail')['text'] ?? null)
            ->toBe(t('screen.auth.email_verified.errors.invalid_params'));
    }

    app()->setLocale($originalLocale);
});
