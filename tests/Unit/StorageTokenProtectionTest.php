<?php

use App\Models\User;
use Idei\Usim\Concerns\HandlesScreenProperties;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Facades\Auth;

class DummyScreenWithToken extends Screen
{
    protected string $store_token = '';
    protected string $store_theme = 'dark';

    protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void
    {
    }
}

class DummyScreenWithTokenCrypt extends Screen
{
    protected string $store_token_crypt = '';
    protected string $store_theme = 'dark';

    protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void
    {
    }
}

class DummyScreenWithoutToken extends Screen
{
    protected string $store_theme = 'light';
    protected int $store_counter = 42;

    protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void
    {
    }
}

beforeEach(function () {
    UIStateManager::setAuthToken(null);
    Auth::guard('web')->logout();
});

it('hydrates active token into screen store_token when screen has default empty string and active token exists', function () {
    $token = 'sanctum_token_abc_123';
    UIStateManager::setAuthToken($token);

    $user = User::factory()->create();
    Auth::guard('web')->setUser($user);

    $screen = new DummyScreenWithToken();
    $screen->injectStorageValues([]);

    $storage = $screen->getStorageVariables();

    expect($storage['store_token'])->toBe($token);
    expect($storage['store_theme'])->toBe('dark');
});

it('does not emit empty store_token if user is authenticated via web guard and has active token', function () {
    $token = 'sanctum_token_xyz_789';
    UIStateManager::setAuthToken($token);

    $user = User::factory()->create();
    Auth::guard('web')->setUser($user);

    $screen = new DummyScreenWithToken();
    // Simulate incoming storage without store_token
    $screen->injectStorageValues(['store_theme' => 'light']);

    $storage = $screen->getStorageVariables();

    expect($storage['store_token'])->toBe($token);
});

it('does not emit empty store_token when user is authenticated even if no active token is in UIStateManager', function () {
    $user = User::factory()->create();
    Auth::guard('web')->setUser($user);
    UIStateManager::setAuthToken(null);

    $screen = new DummyScreenWithToken();
    $screen->injectStorageValues([]);

    $storage = $screen->getStorageVariables();

    // Must NOT emit 'store_token' => '' which would clobber client storage
    expect(array_key_exists('store_token', $storage))->toBeFalse();
    expect($storage['store_theme'])->toBe('dark');
});

it('emits empty store_token when user is unauthenticated (after logout)', function () {
    Auth::guard('web')->logout();
    UIStateManager::setAuthToken(null);

    $screen = new DummyScreenWithToken();
    $screen->injectStorageValues([]);

    $storage = $screen->getStorageVariables();

    // When logged out, emitting empty store_token is correct so client clears storage
    expect(array_key_exists('store_token', $storage))->toBeTrue();
    expect($storage['store_token'])->toBe('');
});

it('protects store_token_crypt from being emitted empty when user is authenticated', function () {
    $token = 'secure_sanctum_token_456';
    UIStateManager::setAuthToken($token);

    $user = User::factory()->create();
    Auth::guard('web')->setUser($user);

    $screen = new DummyScreenWithTokenCrypt();
    $screen->injectStorageValues([]);

    $storage = $screen->getStorageVariables();

    expect(array_key_exists('store_token_crypt', $storage))->toBeTrue();
    expect(decrypt($storage['store_token_crypt']))->toBe($token);
});

it('prevents UIChangesCollector from clobbering an existing token with an empty string when user is authenticated', function () {
    $user = User::factory()->create();
    Auth::guard('web')->setUser($user);
    UIStateManager::setAuthToken('active_token_123');

    $collector = new UIChangesCollector();
    $collector->setStorage(['store_token' => 'active_token_123', 'store_theme' => 'dark']);

    // Another secondary component or screen emits empty store_token
    $collector->setStorage(['store_token' => '', 'store_theme' => 'light']);

    $storage = $collector->getStorage();

    expect($storage['store_token'])->toBe('active_token_123');
    expect($storage['store_theme'])->toBe('light');
});

it('allows UIChangesCollector to clear store_token when user is logged out', function () {
    Auth::guard('web')->logout();
    UIStateManager::setAuthToken(null);

    $collector = new UIChangesCollector();
    $collector->setStorage(['store_token' => 'active_token_123']);

    // Logout action emits empty store_token
    $collector->setStorage(['store_token' => '']);

    $storage = $collector->getStorage();
    expect($storage['store_token'])->toBe('');
});

