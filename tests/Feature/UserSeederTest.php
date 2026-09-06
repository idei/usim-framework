<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Idei\Usim\Models\UsimUnit;
use Spatie\Permission\Models\Role;

it('seeds realistic users adhering to role and unit assignment policies', function () {
    // Run the seeder
    $this->seed(UserSeeder::class);

    $users = User::with('usimUnits')->get();
    expect($users->count())->toBeGreaterThanOrEqual(50);

    $registeredUsersCount = 0;
    $nonRegisteredUsersCount = 0;

    foreach ($users as $user) {
        $userUnits = $user->usimUnits;

        // Check if the user is in lobby
        $isInLobby = $userUnits->contains(fn (UsimUnit $u) => $u->slug === 'lobby');

        if ($isInLobby) {
            // 1.a. Registered user:
            // - ONLY in unit "lobby"
            // - ONLY role "registered"
            $registeredUsersCount++;

            expect($userUnits)->toHaveCount(1);
            expect($userUnits->first()->slug)->toBe('lobby');

            if (config('permission.teams')) {
                setPermissionsTeamId($userUnits->first()->id);
            }

            $user->unsetRelation('roles');
            $roles = $user->getRoleNames()->toArray();
            expect($roles)->toEqual(['registered']);
        } else {
            // 1.b. Non-registered user:
            // - Only in units that are NOT of type "system"
            // - Can have other roles, but NEVER "registered"
            $nonRegisteredUsersCount++;

            foreach ($userUnits as $unit) {
                expect($unit->type)->not->toBe('system');
                expect($unit->slug)->not->toBe('lobby');
                expect($unit->slug)->not->toBe('main');

                if (config('permission.teams')) {
                    setPermissionsTeamId($unit->id);
                }

                $user->unsetRelation('roles');
                $roles = $user->getRoleNames()->toArray();

                expect($roles)->not->toBeEmpty();
                expect($roles)->not->toContain('registered');
            }
        }
    }

    if (config('permission.teams')) {
        setPermissionsTeamId(null);
    }

    expect($registeredUsersCount)->toBeGreaterThan(0);
    expect($nonRegisteredUsersCount)->toBeGreaterThan(0);
});

