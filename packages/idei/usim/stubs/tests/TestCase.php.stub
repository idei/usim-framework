<?php

namespace Tests;

use App\Models\User;
use App\UI\Screens\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * @method TestResponse postJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Logs in a configured role user via the real UI event endpoint.
     *
     * @return array{user: User, response: TestResponse, config: array<string, mixed>}
     */
    public function loginAs(string $role): array
    {
        if (!in_array($role, ['admin', 'user'], true)) {
            throw new InvalidArgumentException("Unsupported role '{$role}'. Expected 'admin' or 'user'.");
        }

        Role::findOrCreate($role);

        $userConfig = config("users.roles.{$role}.seed_user", []);
        $firstName = $userConfig['first_name'] ?? ucfirst($role);
        $lastName = $userConfig['last_name'] ?? 'User';
        $email = $userConfig['email'] ?? "{$role}@example.com";
        $password = $userConfig['password'] ?? 'password';

        $user = User::factory()->create([
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'password' => bcrypt($password),
        ]);
        $user->assignRole($role);

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
