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
        /** @var array<string, array<string, mixed>> $rolesData */
        $rolesData = config('usim.roles', []);

        // We sort roles by priority
        uasort($rolesData, static function (array $a, array $b): int {
            $aPriority = $a['priority'] ?? 0;
            $bPriority = $b['priority'] ?? 0;
            $aPriority = is_int($aPriority) ? $aPriority : 0;
            $bPriority = is_int($bPriority) ? $bPriority : 0;

            return $aPriority <=> $bPriority;
        });

        /** @var list<string> $roles */
        $roles = array_keys($rolesData);

        User::factory()
            ->count(self::USERS_COUNT)
            ->create()
            ->each(function (User $user) use ($roles) {
                $roles_to_assign = rand(self::MIN_ROLES_BY_USER, self::MAX_ROLES_BY_USER);
                $rand_roles = Arr::random($roles, $roles_to_assign);

                if (is_array($rand_roles)) {
                    /** @var list<string> $rolesForSync */
                    $rolesForSync = array_values($rand_roles);
                } else {
                    $rolesForSync = is_string($rand_roles) ? [$rand_roles] : [];
                }

                $user->syncRoles($rolesForSync);
            });
    }
}
