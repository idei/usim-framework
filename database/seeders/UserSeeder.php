<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class UserSeeder extends Seeder
{
    protected const USERS_COUNT = 50;
    protected const MIN_ROLES_BY_USER = 1;
    protected const MAX_ROLES_BY_USER = 3;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesData = config('usim.roles', []);

        // We sort roles by priority
        uasort($rolesData, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $roles = array_keys($rolesData);

        User::factory()
            ->count(self::USERS_COUNT)
            ->create()
            ->each(function (User $user) use ($roles) {
                $rand_roles = Arr::random($roles, rand(self::MIN_ROLES_BY_USER, self::MAX_ROLES_BY_USER));
                $user->syncRoles($rand_roles);
            });
    }
}
