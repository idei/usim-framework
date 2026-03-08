<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::count() > 1) {
            return;
        }

        $rolesConfig = config('users.roles', []);

        foreach ($rolesConfig as $roleName => $roleMeta) {
            if (!\is_string($roleName) || $roleName === '') {
                continue;
            }

            $this->createConfigUser($roleName, (array) ($roleMeta['seed_user'] ?? []));
        }

        User::factory(107)->create()->each(function ($user) {
            $user->assignRole('user');
        });
    }

    private function createConfigUser(string $role, array $seedUserConfig = []): void
    {
        $legacyUserConfig = (array) config("users.{$role}", []);
        $userConfig = array_merge($legacyUserConfig, $seedUserConfig);

        if (empty($userConfig['email']) || empty($userConfig['password'])) {
            return;
        }

        $firstName = $userConfig['first_name'] ?? ucfirst($role);
        $lastName = $userConfig['last_name'] ?? 'User';
        $fullName = trim($firstName . ' ' . $lastName);
        $email = $userConfig['email'];
        $password = $userConfig['password'];

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $fullName,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'password' => bcrypt($password)
            ]
        );

        $user->assignRole($role);
    }
}
