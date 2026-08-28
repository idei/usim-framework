<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;

it('returns login screen UI contract with renderable components', function () {
    /** @var \Tests\TestCase $this */
    $response = getScreenJson($this, Login::class);

    $response->assertOk();

    $component = firstUiComponentFromPayload($response->json());
    expect($component)->not->toBeNull();
    expect($component['type'])->toBeString();
    expect($component['parent'])->not->toBeNull();
    expect($component['_json_key'] ?? null)->toBeNumeric();
});

it('redirects guest when requesting profile screen', function () {
    /** @var \Tests\TestCase $this */
    $response = getScreenJson($this, Profile::class);

    $response->assertOk();
    expect($response->json('redirect'))->toContain('/auth/login');
});

it('redirects guest when requesting admin dashboard screen', function () {
    /** @var \Tests\TestCase $this */
    $response = getScreenJson($this, UsersManager::class);

    $response->assertOk();
    expect($response->json('redirect'))->toContain('/auth/login');
});

it('returns abort 403 when authenticated user has no admin role', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    /** @var User $user */
    $this->actingAs($user);

    $response = getScreenJson($this, UsersManager::class);

    $response->assertOk();
    expect($response->json('abort.code'))->toBe(403);
});

it('returns admin dashboard components for admin user without redirect or abort', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $response = getScreenJson($this, UsersManager::class);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
    expect($response->json('abort'))->toBeNull();
    expect(firstUiComponentFromPayload($response->json()))->not->toBeNull();
});
