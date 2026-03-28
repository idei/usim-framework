<?php

use App\UI\Screens\Auth\EmailVerified;
use Idei\Usim\Services\Support\UIStateManager;
use Illuminate\Support\Str;

it('persists the post-load snapshot after reset reloads', function () {
    /** @var \Tests\TestCase $test */
    $test = $this;
    $clientId = (string) Str::uuid();

    $resetResponse = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $clientId)
        ->getJson(screenApiUrl(EmailVerified::class, ['reset' => true]));

    $resetResponse->assertOk();
    expect(findComponentByName($resetResponse->json(), 'loading_message'))->toBeNull();
    expect(findComponentByName($resetResponse->json(), 'error_message')['text'] ?? null)
        ->toBe('Error al verificar el email');

    $cachedResponse = $test
        ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $clientId)
        ->getJson(screenApiUrl(EmailVerified::class));

    $cachedResponse->assertOk();
    expect(findComponentByName($cachedResponse->json(), 'loading_message'))->toBeNull();
    expect(findComponentByName($cachedResponse->json(), 'error_message')['text'] ?? null)
        ->toBe('Error al verificar el email');
});
