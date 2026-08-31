<?php

namespace Tests\Traits;

use App\Models\User;
use App\UI\Screens\Auth\Login;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
            $permissions = \array_merge($permissions, $screenPermission);
        }
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $roleModel->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $defaultUnitSlug = config('usim.units.default', 'main');
        $unit = UsimUnit::firstOrCreate(['slug' => $defaultUnitSlug]);
        setPermissionsTeamId($unit->id);

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

    /**
     * Creates the configured root user along with the
     * role and permissions.
     *
     * @return User
     */
    public function createRootUser(): User
    {
        $role = Role::findOrCreate('root');
        $permissions = config("usim.roles.root.permissions", []);
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $defaultUnitSlug = config('usim.units.default', 'main');
        $unit = UsimUnit::firstOrCreate(['slug' => $defaultUnitSlug]);
        setPermissionsTeamId($unit->id);

        $userConfig = config("usim.users.roles.root.seed_user", []);
        $firstName = $userConfig['first_name'] ?? 'Root';
        $lastName = $userConfig['last_name'] ?? 'User';
        $email = $userConfig['email'] ?? "root@example.com";
        $password = $userConfig['password'] ?? 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('root');
        return $user;
    }

    /**
     * Creates the default registering role along with the role's
     * permissions.
     *
     * @return User
     */
    public function createDefaultUser(): User
    {
        $defaultRole = config('usim.default_registering_role', 'user');
        $role = Role::findOrCreate($defaultRole);
        $permissions = config("usim.roles.{$defaultRole}.permissions", []);
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $defaultUnitSlug = config('usim.units.default', 'main');
        $unit = UsimUnit::firstOrCreate(['slug' => $defaultUnitSlug]);
        setPermissionsTeamId($unit->id);

        $firstName = ucfirst('Default');
        $lastName = 'User';
        $email = "default@example.com";
        $password = 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => null,
        ]);
        $user->assignRole($defaultRole);
        return $user;
    }
}
