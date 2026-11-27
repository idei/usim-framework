<?php

use App\Models\User;
use Idei\Usim\Concerns\HandlesAuthorization;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

if (! class_exists('Tests_Unit_RoleAuthTestScreen')) {
    class Tests_Unit_RoleAuthTestScreen
    {
        use HandlesAuthorization;

        /** @var string|list<string> */
        public static string|array $requiredRoles = 'admin';

        public static ?string $requiredGuard = null;

        public static mixed $requiredUnit = null;

        public static function resetTestConfig(): void
        {
            static::$visibility = Visibility::AUTHENTICATED;
            static::$guard = null;
            static::$requiredRoles = 'admin';
            static::$requiredGuard = null;
            static::$requiredUnit = null;
        }

        public static function authorize(): bool
        {
            return static::requireRole(static::$requiredRoles, static::$requiredGuard, static::$requiredUnit);
        }

        /**
         * @param  string|list<string>  $roles
         */
        public static function callRequireRole(string|array $roles, ?string $guard = null, mixed $unit = null): bool
        {
            return static::requireRole($roles, $guard, $unit);
        }

        protected static function getRoutePath(): string
        {
            return '/test/role-auth';
        }
    }
}

function makeStubAuthGuard(bool $authenticated, mixed $user): Guard
{
    return new class($authenticated, $user) implements Guard
    {
        public function __construct(
            private bool $authenticated,
            private mixed $user
        ) {}

        public function check(): bool
        {
            return $this->authenticated;
        }

        public function guest(): bool
        {
            return ! $this->authenticated;
        }

        public function user(): ?Authenticatable
        {
            return $this->user;
        }

        public function id(): int|string|null
        {
            return $this->user?->getAuthIdentifier();
        }

        public function validate(array $credentials = []): bool
        {
            return false;
        }

        public function hasUser(): bool
        {
            return $this->user !== null;
        }

        public function setUser(Authenticatable $user): static
        {
            $this->user = $user;

            return $this;
        }
    };
}

function makeStubAuthenticatable(
    ?Closure $hasAnyRoleCallback = null,
    ?bool $isRoot = null,
    bool $withHasAnyRole = true,
    bool $withIsRoot = false,
): Authenticatable {
    if ($withIsRoot) {
        return new class($hasAnyRoleCallback, (bool) $isRoot) implements Authenticatable
        {
            public function __construct(
                private ?Closure $hasAnyRoleCallback,
                private bool $rootFlag
            ) {}

            public function isRoot(): bool
            {
                return $this->rootFlag;
            }

            public function hasAnyRole(string|array $roles): bool
            {
                return $this->hasAnyRoleCallback !== null
                    ? (bool) ($this->hasAnyRoleCallback)($roles)
                    : false;
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 999;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }

    if (! $withHasAnyRole) {
        return new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 998;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }

    return new class($hasAnyRoleCallback) implements Authenticatable
    {
        public function __construct(
            private ?Closure $hasAnyRoleCallback
        ) {}

        public function hasAnyRole(string|array $roles): bool
        {
            return $this->hasAnyRoleCallback !== null
                ? (bool) ($this->hasAnyRoleCallback)($roles)
                : false;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 997;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

describe('HandlesAuthorization::requireRole', function () {
    beforeEach(function () {
        Tests_Unit_RoleAuthTestScreen::resetTestConfig();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
    });

    afterEach(function () {
        Tests_Unit_RoleAuthTestScreen::resetTestConfig();
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
    });

    it('denies access when no user is authenticated', function (string|array $roles) {
        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole($roles))->toBeFalse();
    })->with([
        'single role string' => ['admin'],
        'multiple roles array' => [['admin', 'translator']],
        'empty roles array' => [[]],
    ]);

    it('returns redirect response from checkAccess when unauthenticated on web guard', function () {
        Tests_Unit_RoleAuthTestScreen::$requiredRoles = 'admin';

        $result = Tests_Unit_RoleAuthTestScreen::checkAccess();

        expect($result)->toBe([
            'allowed' => false,
            'action' => 'redirect',
            'params' => [
                'url' => url('/auth/login'),
                'message' => 'Please authenticate to access this page.',
            ],
        ]);
    });

    it('returns device pairing redirect from checkAccess when unauthenticated on device guard', function () {
        Auth::extend('unauth-device-stub', fn () => makeStubAuthGuard(false, null));
        config(['auth.guards.device' => ['driver' => 'unauth-device-stub']]);

        Tests_Unit_RoleAuthTestScreen::$guard = 'device';
        Tests_Unit_RoleAuthTestScreen::$requiredRoles = 'smart_tv';

        $result = Tests_Unit_RoleAuthTestScreen::checkAccess();

        expect($result)->toBe([
            'allowed' => false,
            'action' => 'redirect',
            'params' => [
                'url' => url('/device/device-pairing-screen'),
                'message' => 'Please authenticate to access this page.',
            ],
        ]);
    });

    it('returns false when guard check passes but resolved user is null', function () {
        Auth::extend('null-user-stub', fn () => makeStubAuthGuard(true, null));
        config(['auth.guards.null_user' => ['driver' => 'null-user-stub']]);

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', 'null_user'))->toBeFalse();
    });

    it('checks single and multiple roles for an authenticated user in the active unit', function (string|array $requiredRoles, bool $expected) {
        $unit = UsimUnit::create(['slug' => 'alpha-unit', 'type' => 'department']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'translator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        $user = User::factory()->create();
        setPermissionsTeamId($unit->id);
        $user->assignRole($roleAdmin);

        $this->actingAs($user, 'web');

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole($requiredRoles))->toBe($expected);
    })->with([
        'matching single role string' => ['admin', true],
        'non-matching single role string' => ['translator', false],
        'array with one matching role' => [['translator', 'admin'], true],
        'array with no matching roles' => [['translator', 'member'], false],
        'empty roles array' => [[], false],
    ]);

    it('returns allowed and 403 abort responses from checkAccess for authenticated users', function () {
        $unit = UsimUnit::create(['slug' => 'check-access-unit', 'type' => 'department']);
        $roleMember = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        $user = User::factory()->create();
        setPermissionsTeamId($unit->id);
        $user->assignRole($roleMember);
        $this->actingAs($user, 'web');

        Tests_Unit_RoleAuthTestScreen::$requiredRoles = 'member';
        expect(Tests_Unit_RoleAuthTestScreen::checkAccess())->toBe([
            'allowed' => true,
            'action' => null,
            'params' => [],
        ]);

        Tests_Unit_RoleAuthTestScreen::$requiredRoles = ['admin', 'translator'];
        expect(Tests_Unit_RoleAuthTestScreen::checkAccess())->toBe([
            'allowed' => false,
            'action' => 'abort',
            'params' => [
                'code' => 403,
                'message' => 'Unauthorized: Insufficient permissions.',
            ],
        ]);
    });

    it('always grants access when authenticated user is root', function () {
        $rootUnit = UsimUnit::create(['slug' => 'main-root-unit', 'type' => 'system']);
        $otherUnit = UsimUnit::create(['slug' => 'other-unit', 'type' => 'department']);
        $roleRoot = Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $rootUser = User::factory()->create();
        setPermissionsTeamId($rootUnit->id);
        $rootUser->assignRole($roleRoot);
        $this->actingAs($rootUser, 'web');

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('non_existent_role'))->toBeTrue()
            ->and(Tests_Unit_RoleAuthTestScreen::callRequireRole(['role_a', 'role_b'], null, $otherUnit))->toBeTrue();
    });

    it('falls back to role check when user has isRoot returning false or lacks isRoot', function () {
        $nonRootUser = makeStubAuthenticatable(
            hasAnyRoleCallback: fn (string|array $roles): bool => in_array('editor', (array) $roles, true),
            isRoot: false,
            withIsRoot: true,
        );

        Auth::extend('non-root-stub', fn () => makeStubAuthGuard(true, $nonRootUser));
        config(['auth.guards.non_root' => ['driver' => 'non-root-stub']]);

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('editor', 'non_root'))->toBeTrue()
            ->and(Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', 'non_root'))->toBeFalse();

        $plainUser = makeStubAuthenticatable(
            hasAnyRoleCallback: fn (string|array $roles): bool => $roles === 'viewer',
            withIsRoot: false,
        );

        Auth::extend('plain-user-stub', fn () => makeStubAuthGuard(true, $plainUser));
        config(['auth.guards.plain_user' => ['driver' => 'plain-user-stub']]);

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('viewer', 'plain_user'))->toBeTrue()
            ->and(Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', 'plain_user'))->toBeFalse();
    });

    it('returns false when authenticated user object does not implement hasAnyRole', function () {
        $userWithoutRoles = makeStubAuthenticatable(withHasAnyRole: false);

        Auth::extend('no-roles-method-stub', fn () => makeStubAuthGuard(true, $userWithoutRoles));
        config(['auth.guards.no_roles_method' => ['driver' => 'no-roles-method-stub']]);

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', 'no_roles_method'))->toBeFalse();
    });

    it('honors an explicit guard independently from the default web guard', function () {
        $deviceActor = makeStubAuthenticatable(
            hasAnyRoleCallback: fn (string|array $roles): bool => in_array('smart_tv', (array) $roles, true),
        );

        Auth::extend('custom-device-stub', fn () => makeStubAuthGuard(true, $deviceActor));
        config(['auth.guards.custom_device' => ['driver' => 'custom-device-stub']]);

        // Not logged into default 'web' guard, only authenticated on 'custom_device'
        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('smart_tv'))->toBeFalse()
            ->and(Tests_Unit_RoleAuthTestScreen::callRequireRole('smart_tv', 'custom_device'))->toBeTrue()
            ->and(Tests_Unit_RoleAuthTestScreen::callRequireRole('sensor', 'custom_device'))->toBeFalse();
    });

    it('scopes role checks by unit instance, unit ID, or unit slug and restores previous team ID', function (string $unitMode, bool $expected) {
        $unitA = UsimUnit::create(['slug' => 'unit-alpha', 'type' => 'department']);
        $unitB = UsimUnit::create(['slug' => 'unit-beta', 'type' => 'department']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        setPermissionsTeamId($unitA->id);
        $user->assignRole($roleAdmin);

        // Switch active team to unitB where the user has no admin role
        setPermissionsTeamId($unitB->id);
        $freshUser = User::findOrFail($user->id);
        $this->actingAs($freshUser, 'web');

        $unitArg = match ($unitMode) {
            'unitA_instance' => $unitA,
            'unitA_id' => $unitA->id,
            'unitA_slug' => 'unit-alpha',
            'unitB_instance' => $unitB,
            'unitB_id' => $unitB->id,
            'unitB_slug' => 'unit-beta',
            'unknown_slug' => 'does-not-exist',
            'invalid_zero' => 0,
            'empty_string' => '',
        };

        expect(Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', null, $unitArg))->toBe($expected)
            ->and(getPermissionsTeamId())->toBe($unitB->id);
    })->with([
        'matching UsimUnit instance' => ['unitA_instance', true],
        'matching integer unit ID' => ['unitA_id', true],
        'matching unit slug' => ['unitA_slug', true],
        'non-matching UsimUnit instance' => ['unitB_instance', false],
        'non-matching integer unit ID' => ['unitB_id', false],
        'non-matching unit slug' => ['unitB_slug', false],
        'unknown unit slug falls back to current team' => ['unknown_slug', false],
        'zero unit ID falls back to current team' => ['invalid_zero', false],
        'empty unit string falls back to current team' => ['empty_string', false],
    ]);

    it('restores previous permissions team ID even when hasAnyRole throws an exception', function () {
        $unitA = UsimUnit::create(['slug' => 'unit-throwing', 'type' => 'department']);
        $unitB = UsimUnit::create(['slug' => 'unit-previous', 'type' => 'department']);

        $seenTeamId = null;
        $throwingUser = makeStubAuthenticatable(
            hasAnyRoleCallback: function () use (&$seenTeamId): bool {
                $seenTeamId = getPermissionsTeamId();
                throw new RuntimeException('Simulated role check failure');
            }
        );

        Auth::extend('throwing-role-stub', fn () => makeStubAuthGuard(true, $throwingUser));
        config(['auth.guards.throwing_role' => ['driver' => 'throwing-role-stub']]);

        setPermissionsTeamId($unitB->id);

        expect(fn () => Tests_Unit_RoleAuthTestScreen::callRequireRole('admin', 'throwing_role', $unitA->id))
            ->toThrow(RuntimeException::class, 'Simulated role check failure');

        expect($seenTeamId)->toBe($unitA->id)
            ->and(getPermissionsTeamId())->toBe($unitB->id);
    });
});
