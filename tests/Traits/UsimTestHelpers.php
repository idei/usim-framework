<?php

namespace Tests\Traits;

use App\Models\User;
use App\UI\Screens\Auth\Login;
use Idei\Usim\Screen;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * @mixin \Tests\TestCase
 */
trait UsimTestHelpers
{
    // use RefreshDatabase;

    /**
     * Logs in a configured role user via the real UI event endpoint.
     *
     * @param string $role
     * @param class-string<Screen>|null $withScreenPermission
     * @return array{user: User, response: \Illuminate\Testing\TestResponse, config: array<string, mixed>}
     */
    public function loginAs(string $role, ?string $withScreenPermission = null): array
    {
        $allowedRoles = array_keys(config('usim.roles', []));
        if (!\in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException("Unsupported role '{$role}'. Expected one of: " . implode(', ', $allowedRoles) . ".");
        }

        $roleModel = Role::findOrCreate($role);

        $permissions = config("usim.roles.{$role}.permissions", []);
        if ($withScreenPermission !== null) {
            $screenPermission = array_keys($withScreenPermission::resolvedPermissions());
            $permissions = array_merge($permissions, $screenPermission);
        }
        foreach ($permissions as $permName) {
            $permission = \Spatie\Permission\Models\Permission::findOrCreate($permName);
            $roleModel->givePermissionTo($permission);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $userConfig = config("usim.users.roles.{$role}.seed_user", config("users.roles.{$role}.seed_user", []));
        $firstName = $userConfig['first_name'] ?? ucfirst($role);
        $lastName = $userConfig['last_name'] ?? 'User';
        $email = $userConfig['email'] ?? "{$role}@example.com";
        $password = $userConfig['password'] ?? 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
        ]);
        $user->assignRole($role);

        /** @var \Illuminate\Testing\TestResponse $uiResponse */
        $uiResponse = getScreenJson($this, Login::class);
        $uiResponse->assertOk();
        $componentId = serviceRootComponentId($uiResponse->json());

        $response = $this->postJson('/api/ui-event', [
            'component_id' => $componentId,
            'event' => 'click',
            'action' => 'submit_login',
            'parameters' => [
                'login_email' => $email,
                'login_password' => $password,
            ],
        ]);

        return [
            'user' => $user,
            'response' => $response,
            'config' => $userConfig,
        ];
    }
}
