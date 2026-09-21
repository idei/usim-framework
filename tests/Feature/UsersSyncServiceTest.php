<?php

use App\Models\User;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\Config\UserConfig;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\Support\UsersSyncService;
use Tests\TestCase;

beforeEach(function () {
    config(['permission.teams' => true]);
});

it('creates the root user with proper roles and units', function () {
    config([
        'permission.teams' => true,
        'usim.users' => [
            'root' => [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'root@example.com',
                'password' => 'SuperSecret123!',
                'unit_roles' => [
                    'main' => ['root'],
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    User::where('email', 'root@example.com')->delete();

    $service = new UsersSyncService();
    $stats = $service->sync();

    expect($stats['users_created'])->toBe(1);
    expect($stats['users_updated'])->toBe(0);

    $user = User::where('email', 'root@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Super Admin');

    $mainUnit = UsimUnit::where('slug', 'main')->first();
    expect($mainUnit)->not->toBeNull();

    setPermissionsTeamId($mainUnit->id);
    expect($user->hasRole('root'))->toBeTrue();
    setPermissionsTeamId(null);
});

it('updates an existing root user without duplicating records', function () {
    config([
        'permission.teams' => true,
        'usim.users' => [
            'root' => [
                'first_name' => 'Initial',
                'last_name' => 'Root',
                'email' => 'root_update@example.com',
                'password' => 'Secret123!',
                'unit_roles' => [
                    'main' => ['root'],
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $service = new UsersSyncService();
    $statsFirst = $service->sync();
    expect($statsFirst['users_created'])->toBe(1);

    // Update config first/last name
    config([
        'usim.users.root.first_name' => 'Updated',
        'usim.users.root.last_name' => 'Admin',
    ]);
    app()->forgetInstance(UsimConfig::class);

    $serviceUpdated = new UsersSyncService();
    $statsSecond = $serviceUpdated->sync();

    expect($statsSecond['users_created'])->toBe(0);
    expect($statsSecond['users_updated'])->toBe(1);

    $user = User::where('email', 'root_update@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Updated Admin');
});

it('throws an exception when root password is CHANGE_ME', function () {
    config([
        'usim.users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'User',
                'email' => 'root@example.com',
                'password' => 'CHANGE_ME',
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $service = new UsersSyncService();
    $service->sync();
})->throws(\RuntimeException::class, 'ROOT_PASSWORD must be set (not CHANGE_ME) to install USIM.');

it('throws an exception when root email is invalid', function () {
    config([
        'usim.users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'User',
                'email' => 'invalid-email',
                'password' => 'ValidPass123!',
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $service = new UsersSyncService();
    $service->sync();
})->throws(\RuntimeException::class, 'ROOT_EMAIL must be a valid email to install USIM.');

it('syncs configured non-root users and assigns their unit roles', function () {
    config([
        'permission.teams' => true,
        'usim.users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'User',
                'email' => 'root@example.com',
                'password' => 'ValidPass123!',
                'unit_roles' => [
                    'main' => ['root'],
                ],
            ],
            'editor' => [
                'first_name' => 'John',
                'last_name' => 'Editor',
                'email' => 'editor@example.com',
                'password' => 'EditorPass123!',
                'unit_roles' => [
                    'news' => ['editor'],
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    User::whereIn('email', ['root@example.com', 'editor@example.com'])->delete();

    $service = new UsersSyncService();
    $stats = $service->sync();

    expect($stats['users_created'])->toBe(2);

    $editor = User::where('email', 'editor@example.com')->first();
    expect($editor)->not->toBeNull();
    expect($editor->name)->toBe('John Editor');

    $newsUnit = UsimUnit::where('slug', 'news')->first();
    expect($newsUnit)->not->toBeNull();

    setPermissionsTeamId($newsUnit->id);
    expect($editor->hasRole('editor'))->toBeTrue();
    setPermissionsTeamId(null);
});

it('skips non-root configured users with invalid email or empty password without throwing', function () {
    config([
        'permission.teams' => true,
        'usim.users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'User',
                'email' => 'root@example.com',
                'password' => 'ValidPass123!',
                'unit_roles' => [
                    'main' => ['root'],
                ],
            ],
            'invalid_user' => [
                'first_name' => 'Invalid',
                'last_name' => 'User',
                'email' => 'not-an-email',
                'password' => 'SomePass123!',
            ],
            'change_me_user' => [
                'first_name' => 'Change',
                'last_name' => 'Me',
                'email' => 'changeme@example.com',
                'password' => 'CHANGE_ME',
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    User::where('email', 'root@example.com')->delete();

    $service = new UsersSyncService();
    // Does not throw
    $stats = $service->sync();

    expect($stats['users_created'])->toBe(1); // Only root created
    expect(User::where('email', 'changeme@example.com')->exists())->toBeFalse();
});
