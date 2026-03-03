<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

function firstUiComponentFromPayload(array $payload): ?array
{
    $reservedKeys = ['storage', 'action', 'redirect', 'toast', 'abort', 'modal', 'update_modal'];

    foreach ($payload as $key => $value) {
        if (in_array((string) $key, $reservedKeys, true)) {
            continue;
        }

        if (is_array($value) && isset($value['type'], $value['parent'], $value['_id'])) {
            return $value;
        }
    }

    return null;
}

it('returns login screen UI contract with renderable components', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/ui/auth/login');

    $response->assertOk();

    $component = firstUiComponentFromPayload($response->json());
    expect($component)->not->toBeNull();
    expect($component['type'])->toBeString();
    expect($component['parent'])->not->toBeNull();
    expect($component['_id'])->toBeNumeric();
});

it('redirects guest when requesting profile screen', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/ui/auth/profile');

    $response->assertOk();
    expect($response->json('redirect'))->toContain('/auth/login');
});

it('redirects guest when requesting admin dashboard screen', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/ui/admin/dashboard');

    $response->assertOk();
    expect($response->json('redirect'))->toContain('/auth/login');
});

it('returns abort 403 when authenticated user has no admin role', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    /** @var User $user */
    $this->actingAs($user);

    $response = $this->getJson('/api/ui/admin/dashboard');

    $response->assertOk();
    expect($response->json('abort.code'))->toBe(403);
});

it('returns admin dashboard components for admin user without redirect or abort', function () {
    /** @var \Tests\TestCase $this */
    Role::findOrCreate('admin');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    /** @var User $admin */
    $this->actingAs($admin);

    $response = $this->getJson('/api/ui/admin/dashboard');

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
    expect($response->json('abort'))->toBeNull();
    expect(firstUiComponentFromPayload($response->json()))->not->toBeNull();
});
