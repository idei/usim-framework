<?php

use App\Models\User;
use Idei\Usim\Console\Commands\Support\SeedAccessControl;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * Holds isolated filesystem and seeder state for the currently executing test.
 *
 * @param  array{
 *     originalLangPath: string,
 *     originalConfigPath: string,
 *     originalTeamId: int|string|null,
 *     tempRoot: string,
 *     tempLangPath: string,
 *     tempConfigPath: string,
 *     seeder: SeedAccessControl
 * }|null  $state
 * @return array{
 *     originalLangPath: string,
 *     originalConfigPath: string,
 *     originalTeamId: int|string|null,
 *     tempRoot: string,
 *     tempLangPath: string,
 *     tempConfigPath: string,
 *     seeder: SeedAccessControl
 * }|null
 */
function seedAccessControlTestState(?array $state = null, bool $clear = false): ?array
{
    /** @var array{
     *     originalLangPath: string,
     *     originalConfigPath: string,
     *     originalTeamId: int|string|null,
     *     tempRoot: string,
     *     tempLangPath: string,
     *     tempConfigPath: string,
     *     seeder: SeedAccessControl
     * }|null $current
     */
    static $current = null;

    if ($clear) {
        $previous = $current;
        $current = null;

        return $previous;
    }

    if ($state !== null) {
        $current = $state;
    }

    return $current;
}

function seedAccessControl(): SeedAccessControl
{
    $state = seedAccessControlTestState();
    assert($state !== null);

    return $state['seeder'];
}

function seedTempLangPath(): string
{
    $state = seedAccessControlTestState();
    assert($state !== null);

    return $state['tempLangPath'];
}

function seedTempConfigPath(): string
{
    $state = seedAccessControlTestState();
    assert($state !== null);

    return $state['tempConfigPath'];
}

/**
 * Writes a temporary published config/usim.php file in the isolated config directory.
 *
 * @param  array<string, mixed>|string  $payload
 */
function writeTempUsimConfig(string $configDir, array|string $payload): void
{
    File::ensureDirectoryExists($configDir);
    $path = $configDir.'/usim.php';

    if (is_string($payload)) {
        File::put($path, $payload);

        return;
    }

    File::put($path, "<?php\n\nreturn ".var_export($payload, true).";\n");
}

/**
 * Default pass-through published config that nullifies package-level sections so
 * runtime config('usim.*') controls each test deterministically without leakage
 * from packages/idei/usim/config/usim.php.
 *
 * @return array<string, mixed>
 */
function passThroughUsimConfig(): array
{
    return [
        'units' => null,
        'users' => null,
        'roles' => null,
        'permissions' => null,
        'i18n' => [
            'fallback_locale' => null,
            'languages' => null,
            'i18n_key_prefixes' => null,
        ],
    ];
}

/**
 * Valid root user env payload for convenience across tests.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function validRootEnv(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Root',
        'last_name' => 'Admin',
        'email' => 'root@example.test',
        'password' => 'SuperSecret123!',
        'unit' => 'main',
    ], $overrides);
}

/**
 * Minimal baseline runtime config for isolated tests.
 *
 * @param  array<string, mixed>  $overrides
 */
function setMinimalUsimRuntimeConfig(array $overrides = []): void
{
    $base = [
        'units' => [
            'structure' => [
                'main' => ['type' => 'system', 'parent' => null],
            ],
        ],
        'users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'Admin',
                'email' => 'root@example.test',
                'password' => 'SuperSecret123!',
                'unit_roles' => [
                    'main' => ['root'],
                ],
            ],
        ],
        'roles' => [
            'root' => [
                'priority' => 1,
                'home_screen' => 'home',
                'permissions' => ['system.access'],
            ],
        ],
        'permissions' => [
            'system.access' => [],
        ],
        'i18n' => [
            'fallback_locale' => 'en',
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
            ],
            'i18n_key_prefixes' => [
                'role' => 'role.',
                'permission' => 'permission.',
            ],
        ],
    ];

    foreach ($overrides as $key => $value) {
        $base[$key] = $value;
    }

    config([
        'permission.teams' => true,
        'auth.defaults.guard' => 'web',
        'usim' => $base,
    ]);
}

beforeEach(function () {
    $originalLangPath = app()->langPath();
    $originalConfigPath = app()->configPath();
    $originalTeamId = getPermissionsTeamId();

    $tempRoot = sys_get_temp_dir().'/usim_seed_access_control_'.bin2hex(random_bytes(8));
    $tempLangPath = $tempRoot.'/lang';
    $tempConfigPath = $tempRoot.'/config';

    File::makeDirectory($tempLangPath, 0755, true);
    File::makeDirectory($tempConfigPath, 0755, true);

    app()->useLangPath($tempLangPath);
    app()->useConfigPath($tempConfigPath);

    writeTempUsimConfig($tempConfigPath, passThroughUsimConfig());
    setMinimalUsimRuntimeConfig();

    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    seedAccessControlTestState([
        'originalLangPath' => $originalLangPath,
        'originalConfigPath' => $originalConfigPath,
        'originalTeamId' => $originalTeamId,
        'tempRoot' => $tempRoot,
        'tempLangPath' => $tempLangPath,
        'tempConfigPath' => $tempConfigPath,
        'seeder' => new SeedAccessControl,
    ]);
});

afterEach(function () {
    $state = seedAccessControlTestState(clear: true);

    setPermissionsTeamId($state['originalTeamId'] ?? null);

    if ($state !== null) {
        app()->useLangPath($state['originalLangPath']);
        app()->useConfigPath($state['originalConfigPath']);

        if (File::exists($state['tempRoot'])) {
            File::deleteDirectory($state['tempRoot']);
        }
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('units synchronization (upsertUnits)', function () {
    it('creates root units and unordered parent-child hierarchies, writes translations, and emits output lines', function () {
        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    'api' => [
                        'parent' => 'backend',
                        'type' => 'squad',
                        'default_translations' => [
                            'en' => ['display_name' => 'API Squad'],
                        ],
                    ],
                    'backend' => [
                        'parent' => 'engineering',
                        'type' => 'team',
                        'default_translations' => [
                            'en' => ['description' => 'Backend services team'],
                        ],
                    ],
                    'engineering' => [
                        'parent' => null,
                        'type' => 'department',
                        'default_translations' => [
                            'en' => ['display_name' => 'Engineering', 'description' => 'Engineering dept'],
                            'es' => ['display_name' => 'Ingeniería', 'description' => 'Depto de ingeniería'],
                        ],
                    ],
                    'main' => [
                        'type' => 'system',
                    ],
                    'untyped' => [
                        'type' => 12345,
                    ],
                ],
            ],
        ]);

        $lines = [];
        seedAccessControl()->seed(validRootEnv(), User::class, function (string $msg) use (&$lines): void {
            $lines[] = $msg;
        });

        $engineering = UsimUnit::where('slug', 'engineering')->firstOrFail();
        $backend = UsimUnit::where('slug', 'backend')->firstOrFail();
        $api = UsimUnit::where('slug', 'api')->firstOrFail();
        $untyped = UsimUnit::where('slug', 'untyped')->firstOrFail();

        expect($engineering->type)->toBe('department')
            ->and($engineering->parent_id)->toBeNull();

        expect($backend->type)->toBe('team')
            ->and($backend->parent_id)->toBe($engineering->id);

        expect($api->type)->toBe('squad')
            ->and($api->parent_id)->toBe($backend->id);

        expect($untyped->type)->toBeNull()
            ->and($untyped->parent_id)->toBeNull();

        $enUnitFile = seedTempLangPath().'/en/unit.php';
        $esUnitFile = seedTempLangPath().'/es/unit.php';

        expect(File::exists($enUnitFile))->toBeTrue()
            ->and(File::exists($esUnitFile))->toBeTrue();

        $enUnits = require $enUnitFile;
        $esUnits = require $esUnitFile;

        expect($enUnits['engineering'])->toBe([
            'display_name' => 'Engineering',
            'description' => 'Engineering dept',
        ])->and($enUnits['api'])->toBe([
            'display_name' => 'API Squad',
        ])->and($enUnits['backend'])->toBe([
            'description' => 'Backend services team',
        ])->and($esUnits['engineering'])->toBe([
            'display_name' => 'Ingeniería',
            'description' => 'Depto de ingeniería',
        ]);

        expect($lines)->toContain('  ↳ Synchronizing roles and permissions...')
            ->toContain('    ✓ Roles and permissions synchronized via service.')
            ->toContain('  ↳ Synchronizing organizational units...')
            ->toContain('    ✓ Organizational units synchronized successfully.')
            ->toContain('  ↳ Role and permission default translations synced into lang files.');
    });

    it('updates existing units in place without duplicating records', function () {
        $hq = UsimUnit::create(['slug' => 'hq', 'type' => 'legacy']);
        $ops = UsimUnit::create(['slug' => 'ops', 'type' => 'old_team', 'parent_id' => null]);

        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    'main' => ['type' => 'system'],
                    'hq' => ['type' => 'headquarters', 'parent' => null],
                    'ops' => ['type' => 'operations', 'parent' => 'hq'],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), User::class);

        expect(UsimUnit::count())->toBe(3);

        $hq->refresh();
        $ops->refresh();

        expect($hq->type)->toBe('headquarters')
            ->and($hq->parent_id)->toBeNull()
            ->and($ops->type)->toBe('operations')
            ->and($ops->parent_id)->toBe($hq->id);
    });

    it('skips invalid slugs, handles scalar unit entries, and leaves missing parent references as null', function () {
        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    0 => ['type' => 'numeric-key'],
                    '   ' => ['type' => 'blank-key'],
                    'main' => ['type' => 'system'],
                    'bare_unit' => 'not-an-array',
                    'orphan' => [
                        'type' => 'department',
                        'parent' => 'non_existent_parent',
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), User::class);

        expect(UsimUnit::pluck('slug')->sort()->values()->all())
            ->toBe(['bare_unit', 'main', 'orphan']);

        $bare = UsimUnit::where('slug', 'bare_unit')->firstOrFail();
        $orphan = UsimUnit::where('slug', 'orphan')->firstOrFail();

        expect($bare->type)->toBeNull()
            ->and($bare->parent_id)->toBeNull()
            ->and($orphan->type)->toBe('department')
            ->and($orphan->parent_id)->toBeNull();
    });

    it('returns early when units configuration is invalid or empty', function (mixed $unitsConfig) {
        setMinimalUsimRuntimeConfig([
            'units' => $unitsConfig,
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
            ],
        ]);

        $lines = [];
        seedAccessControl()->seed(validRootEnv(), User::class, function (string $msg) use (&$lines): void {
            $lines[] = $msg;
        });

        expect($lines)->not->toContain('  ↳ Synchronizing organizational units...');
        // Only 'main' is created later by upsertSingleUser when syncing root's unit_roles.
        expect(UsimUnit::pluck('slug')->all())->toBe(['main']);
    })->with([
        'non-array units config' => ['invalid-units'],
        'empty structure array' => [['structure' => []]],
        'non-array structure value' => [['structure' => 'invalid-structure']],
    ]);
});

describe('users, unit roles, and guards', function () {
    it('creates root user from explicit env values and configured users with unit memberships and roles', function () {
        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    'main' => ['type' => 'system'],
                    'editorial' => ['type' => 'department'],
                    'support' => ['type' => 'department'],
                ],
            ],
            'users' => [
                '' => [
                    'email' => 'ignored-empty-key@example.test',
                    'password' => 'Secret123!',
                ],
                'roles' => [
                    'email' => 'ignored-roles-key@example.test',
                    'password' => 'Secret123!',
                ],
                'root' => [
                    'unit_roles' => [
                        'main' => ['root'],
                    ],
                ],
                'editor' => [
                    'first_name' => 'Elena',
                    'last_name' => 'Vega',
                    'email' => 'elena@example.test',
                    'password' => 'EditorPass123!',
                    'unit_roles' => [
                        'editorial' => ['editor', 'reviewer'],
                        'support' => ['agent'],
                    ],
                ],
            ],
        ]);

        $stats = seedAccessControl()->seed(validRootEnv([
            'first_name' => 'Super',
            'last_name' => 'Root',
            'email' => 'superroot@example.test',
            'password' => 'RootSecret99!',
        ]), User::class);

        expect($stats['users_created'])->toBe(2)
            ->and($stats['users_updated'])->toBe(0);

        expect(User::whereIn('email', ['ignored-empty-key@example.test', 'ignored-roles-key@example.test'])->exists())
            ->toBeFalse();

        $root = User::where('email', 'superroot@example.test')->firstOrFail();
        expect($root->name)->toBe('Super Root')
            ->and(Hash::check('RootSecret99!', $root->password))->toBeTrue()
            ->and($root->email_verified_at)->not->toBeNull()
            ->and($root->remember_token)->not->toBeNull();

        $mainUnit = UsimUnit::where('slug', 'main')->firstOrFail();
        expect($root->usimUnits()->where('usim_units.id', $mainUnit->id)->exists())->toBeTrue();

        setPermissionsTeamId($mainUnit->id);
        $root->unsetRelation('roles');
        expect($root->getRoleNames()->all())->toBe(['root']);

        $elena = User::where('email', 'elena@example.test')->firstOrFail();
        expect($elena->name)->toBe('Elena Vega')
            ->and(Hash::check('EditorPass123!', $elena->password))->toBeTrue();

        $editorialUnit = UsimUnit::where('slug', 'editorial')->firstOrFail();
        $supportUnit = UsimUnit::where('slug', 'support')->firstOrFail();

        expect($elena->usimUnits()->pluck('slug')->sort()->values()->all())
            ->toBe(['editorial', 'support']);

        setPermissionsTeamId($editorialUnit->id);
        $elena->unsetRelation('roles');
        expect($elena->getRoleNames()->sort()->values()->all())->toBe(['editor', 'reviewer']);

        setPermissionsTeamId($supportUnit->id);
        $elena->unsetRelation('roles');
        expect($elena->getRoleNames()->all())->toBe(['agent']);

        // Missing roles were auto-created through UsimRole::createWithHome
        foreach (['editor', 'reviewer', 'agent'] as $autoRoleName) {
            $role = UsimRole::where('name', $autoRoleName)->where('guard_name', 'web')->firstOrFail();
            expect($role->home_screen)->toBe('welcome')
                ->and($role->priority)->toBe(100);
        }
    });

    it('creates root user from config fallback and normalizes scalar/non-scalar config values when env values are empty', function () {
        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'first_name' => 42,
                    'last_name' => ['not-a-string'],
                    'email' => 'root-config@example.test',
                    'password' => 987654,
                    'unit' => 'hq',
                    'units_roles' => [
                        'hq' => ['root'],
                    ],
                ],
            ],
        ]);

        $stats = seedAccessControl()->seed([], User::class);

        expect($stats['users_created'])->toBe(1)
            ->and($stats['users_updated'])->toBe(0);

        $root = User::where('email', 'root-config@example.test')->firstOrFail();
        expect($root->name)->toBe('42 User')
            ->and(Hash::check('987654', $root->password))->toBeTrue();

        $hq = UsimUnit::where('slug', 'hq')->firstOrFail();
        setPermissionsTeamId($hq->id);
        $root->unsetRelation('roles');
        expect($root->hasRole('root'))->toBeTrue();
    });

    it('updates existing users, preserves existing email_verified_at, fills missing verification timestamp, and resyncs roles', function () {
        $verifiedAt = now()->subDays(5)->startOfSecond();

        $existingVerified = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'member@example.test',
            'password' => bcrypt('OldPass123!'),
            'email_verified_at' => $verifiedAt,
        ]);

        $existingUnverified = User::factory()->create([
            'name' => 'Unverified Old',
            'email' => 'blankname@example.test',
            'password' => bcrypt('OldPass123!'),
            'email_verified_at' => null,
        ]);

        $engUnit = UsimUnit::create(['slug' => 'eng', 'type' => 'department']);
        UsimRole::createWithHome('legacy_role', 'home', 50, 'web');
        setPermissionsTeamId($engUnit->id);
        $existingVerified->syncRoles(['legacy_role']);
        $existingVerified->usimUnits()->syncWithoutDetaching([$engUnit->id]);
        setPermissionsTeamId(null);

        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'member' => [
                    'first_name' => 'Updated',
                    'last_name' => 'Member',
                    'email' => 'member@example.test',
                    'password' => 'NewPass456!',
                    'unit_roles' => [
                        'eng' => ['lead'],
                        'ops' => ['operator'],
                    ],
                ],
                'blankname' => [
                    'first_name' => '   ',
                    'last_name' => '   ',
                    'email' => 'blankname@example.test',
                    'password' => 'Pass789!',
                ],
            ],
        ]);

        $stats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($stats['users_created'])->toBe(1) // root
            ->and($stats['users_updated'])->toBe(2); // member + blankname

        $existingVerified->refresh();
        expect($existingVerified->name)->toBe('Updated Member')
            ->and(Hash::check('NewPass456!', $existingVerified->password))->toBeTrue()
            ->and($existingVerified->email_verified_at)->not->toBeNull()
            ->and(Carbon::parse($existingVerified->email_verified_at)->equalTo($verifiedAt))->toBeTrue()
            ->and($existingVerified->usimUnits()->pluck('slug')->sort()->values()->all())->toBe(['eng', 'ops']);

        setPermissionsTeamId($engUnit->id);
        $existingVerified->unsetRelation('roles');
        expect($existingVerified->getRoleNames()->all())->toBe(['lead']);

        $opsUnit = UsimUnit::where('slug', 'ops')->firstOrFail();
        setPermissionsTeamId($opsUnit->id);
        $existingVerified->unsetRelation('roles');
        expect($existingVerified->getRoleNames()->all())->toBe(['operator']);

        $existingUnverified->refresh();
        expect($existingUnverified->name)->toBe('Blankname')
            ->and($existingUnverified->email_verified_at)->not->toBeNull();
    });

    it('normalizes unit_roles, units_roles, unit, and roles configuration variations', function (
        array $userPayload,
        array $expectedUnitRoles
    ) {
        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'fallback_role' => array_merge([
                    'email' => 'variant@example.test',
                    'password' => 'ValidPass123!',
                ], $userPayload),
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), User::class);

        $user = User::where('email', 'variant@example.test')->firstOrFail();

        $expectedUnits = array_keys($expectedUnitRoles);
        sort($expectedUnits);

        expect($user->usimUnits()->pluck('slug')->sort()->values()->all())
            ->toBe($expectedUnits);

        foreach ($expectedUnitRoles as $unitSlug => $expectedRoles) {
            $unit = UsimUnit::where('slug', $unitSlug)->firstOrFail();
            setPermissionsTeamId($unit->id);
            $user->unsetRelation('roles');

            $actualRoles = $user->getRoleNames()->sort()->values()->all();
            sort($expectedRoles);

            expect($actualRoles)->toBe($expectedRoles);
        }
    })->with([
        'unit_roles array with whitespace, duplicates, and invalid entries' => [
            [
                'unit_roles' => [
                    ' alpha ' => [' editor ', '', 123, 'editor', 'reviewer'],
                ],
            ],
            ['alpha' => ['editor', 'reviewer']],
        ],
        'unit_roles with single string role' => [
            [
                'unit_roles' => [
                    'beta' => 'analyst',
                ],
            ],
            ['beta' => ['analyst']],
        ],
        'unit_roles with empty array falls back to user key' => [
            [
                'unit_roles' => [
                    'gamma' => [],
                ],
            ],
            ['gamma' => ['fallback_role']],
        ],
        'unit_roles with only blank and non-string entries falls back to user key' => [
            [
                'unit_roles' => [
                    'delta' => ['   ', 42],
                ],
            ],
            ['delta' => ['fallback_role']],
        ],
        'unit_roles with non-array non-string role value falls back to user key' => [
            [
                'unit_roles' => [
                    'epsilon' => false,
                ],
            ],
            ['epsilon' => ['fallback_role']],
        ],
        'unit_roles with numeric or blank unit slug defaults to main' => [
            [
                'unit_roles' => [
                    0 => ['viewer'],
                ],
            ],
            ['main' => ['viewer']],
        ],
        'legacy units_roles plural key' => [
            [
                'units_roles' => [
                    'legacy_unit' => 'operator',
                ],
            ],
            ['legacy_unit' => ['operator']],
        ],
        'single unit with string roles' => [
            [
                'unit' => 'ops',
                'roles' => 'deployer',
            ],
            ['ops' => ['deployer']],
        ],
        'single unit with dirty array roles' => [
            [
                'unit' => ' qa ',
                'roles' => [' tester ', '', null, 'tester'],
            ],
            ['qa' => ['tester']],
        ],
        'blank unit with empty roles array falls back to main and user key' => [
            [
                'unit' => '   ',
                'roles' => [],
            ],
            ['main' => ['fallback_role']],
        ],
        'missing unit with only invalid entries in roles falls back to main and user key' => [
            [
                'roles' => ['  ', 99, null],
            ],
            ['main' => ['fallback_role']],
        ],
        'non-array non-string roles value falls back to main and user key' => [
            [
                'roles' => 12345,
            ],
            ['main' => ['fallback_role']],
        ],
    ]);

    it('skips non-root configured users with invalid email or invalid password silently', function () {
        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'bad_email' => [
                    'email' => 'not-an-email',
                    'password' => 'ValidPass123!',
                ],
                'empty_email' => [
                    'email' => '   ',
                    'password' => 'ValidPass123!',
                ],
                'empty_password' => [
                    'email' => 'empty-pass@example.test',
                    'password' => '',
                ],
                'change_me_password' => [
                    'email' => 'changeme@example.test',
                    'password' => 'change_me',
                ],
            ],
        ]);

        $stats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($stats['users_created'])->toBe(1)
            ->and(User::count())->toBe(1)
            ->and(User::where('email', 'root@example.test')->exists())->toBeTrue();
    });

    it('resolves custom guard from user model and falls back cleanly on blank or throwing guards', function () {
        config([
            'auth.guards.api' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'auth.defaults.guard' => '  ',
        ]);

        $apiUserClass = get_class(new class extends User
        {
            protected $table = 'users';

            protected string $guard_name = 'api';

            public function getDefaultGuardName(): string
            {
                return 'api';
            }
        });

        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'api_client' => [
                    'email' => 'api@example.test',
                    'password' => 'ApiPass123!',
                    'unit_roles' => [
                        'main' => ['api_consumer'],
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), $apiUserClass);

        expect(UsimRole::where('name', 'root')->where('guard_name', 'api')->exists())->toBeTrue()
            ->and(UsimRole::where('name', 'api_consumer')->where('guard_name', 'api')->exists())->toBeTrue();

        // Model whose getDefaultGuardName() throws falls back to default auth guard ('web' when blank)
        $throwingGuardModelClass = get_class(new class extends Model
        {
            protected $table = 'users';

            /** @var array<string, list<string>> */
            public static array $syncedByEmail = [];

            public function getDefaultGuardName(): string
            {
                throw new RuntimeException('Guard lookup failed');
            }

            /**
             * @param  array<int, string>  $roles
             */
            public function syncRoles(array $roles): void
            {
                $email = $this->getAttribute('email');
                if (is_string($email)) {
                    self::$syncedByEmail[$email] = array_values($roles);
                }
            }
        });
        $throwingGuardModelClass::$syncedByEmail = [];

        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'fallback_guard_user' => [
                    'email' => 'fallback-guard@example.test',
                    'password' => 'Secret123!',
                    'unit_roles' => [
                        'main' => ['fallback_guard_role'],
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(['email' => 'root-fallback@example.test']), $throwingGuardModelClass);

        expect(UsimRole::where('name', 'fallback_guard_role')->where('guard_name', 'web')->exists())->toBeTrue()
            ->and($throwingGuardModelClass::$syncedByEmail['root-fallback@example.test'])->toBe(['root'])
            ->and($throwingGuardModelClass::$syncedByEmail['fallback-guard@example.test'])->toBe(['fallback_guard_role']);
    });

    it('attaches units via legacy units() relationship when usimUnits() is absent', function () {
        $legacyUnitsUserClass = get_class(new class extends Model
        {
            use HasRoles;

            protected $table = 'users';

            protected string $guard_name = 'web';

            /**
             * @return MorphToMany<UsimUnit, $this>
             */
            public function units(): MorphToMany
            {
                return $this->morphToMany(UsimUnit::class, 'actor', 'usim_unit_actors')->withTimestamps();
            }
        });

        setMinimalUsimRuntimeConfig([
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'legacy_actor' => [
                    'email' => 'legacy-actor@example.test',
                    'password' => 'Secret123!',
                    'unit_roles' => [
                        'branch_a' => ['branch_role'],
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), $legacyUnitsUserClass);

        $actor = $legacyUnitsUserClass::query()->where('email', 'legacy-actor@example.test')->firstOrFail();
        expect($actor->units()->pluck('slug')->all())->toBe(['branch_a']);
    });

    it('aggregates unique roles across all units when permission teams mode is disabled', function () {
        $nonTeamUserClass = get_class(new class extends Model
        {
            protected $table = 'users';

            /** @var array<string, list<string>> */
            public static array $syncedByEmail = [];

            /**
             * @param  array<int, string>  $roles
             */
            public function syncRoles(array $roles): void
            {
                $email = $this->getAttribute('email');
                if (is_string($email)) {
                    self::$syncedByEmail[$email] = array_values($roles);
                }
            }
        });
        $nonTeamUserClass::$syncedByEmail = [];

        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    'should_not_be_created' => ['type' => 'department'],
                ],
            ],
            'users' => [
                'root' => [
                    'unit_roles' => [
                        'main' => ['root'],
                    ],
                ],
                'multi_unit_user' => [
                    'email' => 'multi@example.test',
                    'password' => 'Secret123!',
                    'unit_roles' => [
                        'unit_a' => ['editor', 'reviewer'],
                        'unit_b' => ['reviewer', 'publisher'],
                    ],
                ],
            ],
        ]);
        config(['permission.teams' => false]);

        $stats = seedAccessControl()->seed(validRootEnv(), $nonTeamUserClass);

        expect($stats['users_created'])->toBe(2)
            ->and(UsimUnit::where('slug', 'should_not_be_created')->exists())->toBeFalse()
            ->and($nonTeamUserClass::$syncedByEmail['root@example.test'])->toBe(['root'])
            ->and($nonTeamUserClass::$syncedByEmail['multi@example.test'])->toBe(['editor', 'reviewer', 'publisher']);

        foreach (['root', 'editor', 'reviewer', 'publisher'] as $roleName) {
            expect(UsimRole::where('name', $roleName)->where('guard_name', 'web')->exists())->toBeTrue();
        }
    });

    it('throws RuntimeException for invalid user models or missing/invalid root credentials', function (
        array $rootEnv,
        string $userModelClass,
        string $expectedMessage
    ) {
        expect(fn () => seedAccessControl()->seed($rootEnv, $userModelClass))
            ->toThrow(RuntimeException::class, $expectedMessage);
    })->with(function (): array {
        $modelWithoutHasRoles = get_class(new class extends Model
        {
            protected $table = 'users';
        });

        return [
            'non-existent user model class' => [
                validRootEnv(),
                'App\\Models\\NonExistentUserModel',
                'Configured user model [App\\Models\\NonExistentUserModel] is invalid.',
            ],
            'non-Eloquent class' => [
                validRootEnv(),
                stdClass::class,
                'Configured user model [stdClass] is invalid.',
            ],
            'Eloquent model without HasRoles trait' => [
                validRootEnv(),
                $modelWithoutHasRoles,
                'User model must use Spatie HasRoles trait to sync roles.',
            ],
            'empty root email' => [
                validRootEnv(['email' => '']),
                User::class,
                'ROOT_EMAIL must be a valid email to install USIM.',
            ],
            'malformed root email' => [
                validRootEnv(['email' => 'invalid-email']),
                User::class,
                'ROOT_EMAIL must be a valid email to install USIM.',
            ],
            'empty root password' => [
                validRootEnv(['password' => '']),
                User::class,
                'ROOT_PASSWORD must be set (not CHANGE_ME) to install USIM.',
            ],
            'CHANGE_ME root password (uppercase)' => [
                validRootEnv(['password' => 'CHANGE_ME']),
                User::class,
                'ROOT_PASSWORD must be set (not CHANGE_ME) to install USIM.',
            ],
            'CHANGE_ME root password (lowercase)' => [
                validRootEnv(['password' => 'change_me']),
                User::class,
                'ROOT_PASSWORD must be set (not CHANGE_ME) to install USIM.',
            ],
        ];
    });
});

describe('languages and translations (upsertLanguages & upsertRoleAndPermissionTranslations)', function () {
    it('inserts and updates languages, normalizes codes and defaults, and guarantees a single fallback locale', function () {
        UsimLanguage::create([
            'code' => 'es',
            'name' => 'Old Spanish',
            'native_name' => 'Viejo Español',
            'is_active' => false,
            'is_fallback' => true,
        ]);
        UsimLanguage::create([
            'code' => 'fr',
            'name' => 'French',
            'native_name' => 'Français',
            'is_active' => true,
            'is_fallback' => true,
        ]);

        setMinimalUsimRuntimeConfig([
            'i18n' => [
                'fallback_locale' => ' en ',
                'languages' => [
                    'not-an-array-entry',
                    ['code' => '   ', 'name' => 'Blank Code'],
                    ['name' => 'Missing Code'],
                    ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
                    ['code' => ' es ', 'name' => 'Spanish', 'native_name' => 'Español'],
                    ['code' => 'de', 'name' => '', 'active' => false],
                    ['code' => 'ja', 'active' => false],
                ],
                'i18n_key_prefixes' => [
                    'role' => 'role.',
                    'permission' => 'permission.',
                ],
            ],
        ]);

        $stats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($stats['languages_created'])->toBe(3) // en, de, ja
            ->and($stats['languages_updated'])->toBe(1); // es

        $en = UsimLanguage::where('code', 'en')->firstOrFail();
        $es = UsimLanguage::where('code', 'es')->firstOrFail();
        $de = UsimLanguage::where('code', 'de')->firstOrFail();
        $ja = UsimLanguage::where('code', 'ja')->firstOrFail();
        $fr = UsimLanguage::where('code', 'fr')->firstOrFail();

        expect($en->name)->toBe('English')
            ->and($en->native_name)->toBe('English')
            ->and($en->is_active)->toBeTrue()
            ->and($en->is_fallback)->toBeTrue();

        expect($es->name)->toBe('Spanish')
            ->and($es->native_name)->toBe('Español')
            ->and($es->is_active)->toBeTrue()
            ->and($es->is_fallback)->toBeFalse();

        expect($de->name)->toBe('DE')
            ->and($de->native_name)->toBeNull()
            ->and($de->is_active)->toBeFalse()
            ->and($de->is_fallback)->toBeFalse();

        expect($ja->name)->toBe('JA')
            ->and($ja->native_name)->toBeNull()
            ->and($ja->is_active)->toBeFalse()
            ->and($ja->is_fallback)->toBeFalse();

        expect($fr->is_fallback)->toBeFalse()
            ->and(UsimLanguage::where('is_fallback', true)->count())->toBe(1);
    });

    it('auto-creates or promotes fallback locale when omitted from configured languages', function () {
        // 1. Fallback locale 'pt' does not exist in DB yet -> auto-created
        setMinimalUsimRuntimeConfig([
            'i18n' => [
                'fallback_locale' => 'pt',
                'languages' => [
                    ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
                ],
            ],
        ]);

        $firstStats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($firstStats['languages_created'])->toBe(2) // es + pt
            ->and($firstStats['languages_updated'])->toBe(0);

        $pt = UsimLanguage::where('code', 'pt')->firstOrFail();
        expect($pt->name)->toBe('PT')
            ->and($pt->native_name)->toBe('PT')
            ->and($pt->is_active)->toBeTrue()
            ->and($pt->is_fallback)->toBeTrue();

        // 2. Existing non-fallback language 'it' becomes fallback without being in configured languages -> updated in place
        UsimLanguage::create([
            'code' => 'it',
            'name' => 'Italian',
            'native_name' => 'Italiano',
            'is_active' => false,
            'is_fallback' => false,
        ]);

        setMinimalUsimRuntimeConfig([
            'i18n' => [
                'fallback_locale' => 'it',
                'languages' => [
                    ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
                ],
            ],
        ]);

        $secondStats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($secondStats['languages_created'])->toBe(0)
            ->and($secondStats['languages_updated'])->toBe(2); // es + it

        $it = UsimLanguage::where('code', 'it')->firstOrFail();
        $pt->refresh();

        expect($it->name)->toBe('Italian')
            ->and($it->native_name)->toBe('Italiano')
            ->and($it->is_active)->toBeTrue()
            ->and($it->is_fallback)->toBeTrue()
            ->and($pt->is_fallback)->toBeFalse();
    });

    it('generates and updates role and permission translation files, preserves existing keys, and handles invalid/corrupted lang files', function () {
        // Pre-populate an existing lang file with nested, indexed, and empty arrays to ensure preservation
        File::makeDirectory(seedTempLangPath().'/en', 0755, true);
        File::put(
            seedTempLangPath().'/en/custom_role.php',
            "<?php\n\nreturn [\n    'existing_custom' => ['label' => 'Keep Me', 'tags' => ['alpha', 'beta'], 'empty_bag' => []],\n];\n"
        );

        // Pre-populate a corrupted non-array lang file that should be replaced cleanly
        File::makeDirectory(seedTempLangPath().'/es', 0755, true);
        File::put(seedTempLangPath().'/es/custom_perm.php', "<?php\n\nreturn 'not-an-array';\n");

        setMinimalUsimRuntimeConfig([
            'i18n' => [
                'fallback_locale' => 'en',
                'languages' => [
                    ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
                    ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
                ],
                'i18n_key_prefixes' => [
                    'role' => 'custom_role', // without trailing dot -> normalized to 'custom_role.'
                    'permission' => 'custom_perm.', // with trailing dot
                ],
            ],
            'roles' => [
                0 => ['default_translations' => ['en' => ['display_name' => 'Ignored Numeric']]],
                '   ' => ['default_translations' => ['en' => ['display_name' => 'Ignored Blank']]],
                'scalar_role' => 'not-an-array',
                'root' => [
                    'priority' => 1,
                    'home_screen' => 'home',
                    'permissions' => ['posts.publish'],
                    'default_translations' => [
                        ' en ' => ['display_name' => 'Root User', 'description' => 'Full access'],
                        'es' => ['description' => 'Acceso total'], // missing display_name -> falls back to 'root'
                        0 => ['display_name' => 'Numeric Locale Ignored'],
                        '   ' => ['display_name' => 'Blank Locale Ignored'],
                        'fr' => 'invalid-meta',
                        'de' => ['display_name' => 123, 'description' => ['bad']], // non-string values stripped -> fallbacks applied
                    ],
                ],
                'editor' => [
                    'priority' => 10,
                    'home_screen' => 'home',
                    'default_translations' => 'not-an-array',
                ],
            ],
            'permissions' => [
                0 => ['default_translations' => ['en' => ['display_name' => 'Ignored']]],
                '   ' => ['default_translations' => ['en' => ['display_name' => 'Ignored']]],
                'scalar.perm' => 'not-an-array',
                'posts.publish' => [
                    'default_translations' => [
                        'en' => ['display_name' => 'Publish Posts', 'description' => 'Allows publishing posts.'],
                        'es' => ['display_name' => 'Publicar Entradas'], // missing description -> ''
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), User::class);

        $enRoles = require seedTempLangPath().'/en/custom_role.php';
        $esRoles = require seedTempLangPath().'/es/custom_role.php';
        $deRoles = require seedTempLangPath().'/de/custom_role.php';
        $enPerms = require seedTempLangPath().'/en/custom_perm.php';
        $esPerms = require seedTempLangPath().'/es/custom_perm.php';

        // Existing unrelated keys (including nested, indexed list, and empty array) preserved
        expect($enRoles['existing_custom'])->toBe([
            'label' => 'Keep Me',
            'tags' => ['alpha', 'beta'],
            'empty_bag' => [],
        ]);

        expect($enRoles['root'])->toBe([
            'name' => 'Root User',
            'description' => 'Full access',
        ])->and($esRoles['root'])->toBe([
            'name' => 'root',
            'description' => 'Acceso total',
        ])->and($deRoles['root'])->toBe([
            'name' => 'root',
            'description' => '',
        ]);

        expect(File::exists(seedTempLangPath().'/fr/custom_role.php'))->toBeFalse();

        // Dotted permission key creates nested structure and replaces corrupted non-array file
        expect($enPerms['posts']['publish'])->toBe([
            'name' => 'Publish Posts',
            'description' => 'Allows publishing posts.',
        ])->and($esPerms['posts']['publish'])->toBe([
            'name' => 'Publicar Entradas',
            'description' => '',
        ]);
    });

    it('handles non-array i18n, roles, permissions, and empty translation prefixes gracefully', function () {
        writeTempUsimConfig(seedTempConfigPath(), [
            'units' => null,
            'users' => null,
            'roles' => 'not-an-array',
            'permissions' => 'not-an-array',
            'i18n' => 'not-an-array',
        ]);

        config([
            'permission.teams' => true,
            'app.fallback_locale' => '  ',
            'usim.units' => ['structure' => ['main' => ['type' => 'system']]],
            'usim.users' => ['root' => ['unit_roles' => ['main' => ['root']]]],
            'usim.roles' => 'not-an-array',
            'usim.permissions' => 'not-an-array',
            'usim.i18n' => [
                'fallback_locale' => '   ',
                'languages' => 'not-an-array',
                'i18n_key_prefixes' => 'not-an-array',
            ],
        ]);

        $stats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($stats['roles_total'])->toBe(0)
            ->and($stats['permissions_total'])->toBe(0)
            ->and(UsimLanguage::where('code', 'en')->where('is_fallback', true)->exists())->toBeTrue();

        // Also cover empty translation prefix ('') creating file named after role/permission
        writeTempUsimConfig(seedTempConfigPath(), passThroughUsimConfig());
        setMinimalUsimRuntimeConfig([
            'i18n' => [
                'fallback_locale' => 'en',
                'languages' => [
                    ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
                ],
                'i18n_key_prefixes' => [
                    'role' => '   ',
                    'permission' => null,
                ],
            ],
            'roles' => [
                'root' => [
                    'priority' => 1,
                    'home_screen' => 'home',
                    'default_translations' => [
                        'en' => ['display_name' => 'Root No Prefix', 'description' => 'Desc'],
                    ],
                ],
            ],
        ]);

        seedAccessControl()->seed(validRootEnv(), User::class);

        $noPrefixFile = seedTempLangPath().'/en/root.php';
        expect(File::exists($noPrefixFile))->toBeTrue();
        expect(require $noPrefixFile)->toBe([
            'name' => 'Root No Prefix',
            'description' => 'Desc',
        ]);
    });

    it('loads published config/usim.php overrides and gracefully handles missing or non-array config files', function () {
        // 1. Missing published config file -> falls back to package config cleanly
        File::delete(seedTempConfigPath().'/usim.php');
        expect(File::exists(seedTempConfigPath().'/usim.php'))->toBeFalse();

        $statsMissing = seedAccessControl()->seed(validRootEnv(), User::class);
        expect($statsMissing['roles_total'])->toBeGreaterThan(0)
            ->and($statsMissing['permissions_total'])->toBeGreaterThan(0)
            ->and(UsimUnit::where('slug', 'main')->exists())->toBeTrue();

        // 2. Non-array published config file -> treated as empty array cleanly
        writeTempUsimConfig(seedTempConfigPath(), "<?php\n\nreturn 'corrupted-config';\n");

        $statsCorrupted = seedAccessControl()->seed(validRootEnv(), User::class);
        expect($statsCorrupted['users_updated'])->toBeGreaterThanOrEqual(1);
    });
});

describe('global idempotency', function () {
    it('does not duplicate records and reports update-only stats when seed() is executed twice consecutively', function () {
        setMinimalUsimRuntimeConfig([
            'units' => [
                'structure' => [
                    'main' => [
                        'type' => 'system',
                        'parent' => null,
                        'default_translations' => [
                            'en' => ['display_name' => 'Main', 'description' => 'Main unit'],
                        ],
                    ],
                    'research' => [
                        'type' => 'department',
                        'parent' => 'main',
                        'default_translations' => [
                            'en' => ['display_name' => 'Research', 'description' => 'Research department'],
                        ],
                    ],
                ],
            ],
            'roles' => [
                'root' => [
                    'priority' => 1,
                    'home_screen' => 'home',
                    'permissions' => ['users.manage', 'reports.view'],
                    'default_translations' => [
                        'en' => ['display_name' => 'Root', 'description' => 'Superuser'],
                    ],
                ],
                'researcher' => [
                    'priority' => 20,
                    'home_screen' => 'home',
                    'permissions' => ['reports.view'],
                    'default_translations' => [
                        'en' => ['display_name' => 'Researcher', 'description' => 'Reads reports'],
                    ],
                ],
            ],
            'permissions' => [
                'users.manage' => [
                    'default_translations' => [
                        'en' => ['display_name' => 'Manage Users', 'description' => 'Full user CRUD'],
                    ],
                ],
                'reports.view' => [
                    'default_translations' => [
                        'en' => ['display_name' => 'View Reports', 'description' => 'Read-only reports'],
                    ],
                ],
            ],
            'users' => [
                'root' => [
                    'unit_roles' => ['main' => ['root']],
                ],
                'alice' => [
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                    'email' => 'alice@example.test',
                    'password' => 'AlicePass123!',
                    'unit_roles' => ['research' => ['researcher']],
                ],
                'bob' => [
                    'first_name' => 'Bob',
                    'last_name' => 'Jones',
                    'email' => 'bob@example.test',
                    'password' => 'BobPass123!',
                    'unit_roles' => [
                        'main' => ['researcher'],
                        'research' => ['researcher', 'lab_lead'],
                    ],
                ],
            ],
            'i18n' => [
                'fallback_locale' => 'en',
                'languages' => [
                    ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
                    ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
                    ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'active' => false],
                ],
                'i18n_key_prefixes' => [
                    'role' => 'role.',
                    'permission' => 'permission.',
                ],
            ],
        ]);

        $modelHasRolesTable = config('permission.table_names.model_has_roles');
        assert(is_string($modelHasRolesTable));

        $firstStats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($firstStats)->toBe([
            'permissions_created' => 2,
            'permissions_total' => 2,
            'roles_created' => 2,
            'roles_total' => 2,
            'users_created' => 3,
            'users_updated' => 0,
            'languages_created' => 3,
            'languages_updated' => 0,
        ]);

        $countsAfterFirst = [
            'units' => UsimUnit::count(),
            'roles' => UsimRole::count(),
            'permissions' => Permission::count(),
            'users' => User::count(),
            'languages' => UsimLanguage::count(),
            'unit_actors' => DB::table('usim_unit_actors')->count(),
            'model_has_roles' => DB::table($modelHasRolesTable)->count(),
        ];

        $enUnitAfterFirst = File::get(seedTempLangPath().'/en/unit.php');
        $enRoleAfterFirst = File::get(seedTempLangPath().'/en/role.php');
        $enPermAfterFirst = File::get(seedTempLangPath().'/en/permission.php');

        // Second consecutive execution with the exact same configuration
        $secondStats = seedAccessControl()->seed(validRootEnv(), User::class);

        expect($secondStats)->toBe([
            'permissions_created' => 0,
            'permissions_total' => 2,
            'roles_created' => 0,
            'roles_total' => 2,
            'users_created' => 0,
            'users_updated' => 3,
            'languages_created' => 0,
            'languages_updated' => 3,
        ]);

        $countsAfterSecond = [
            'units' => UsimUnit::count(),
            'roles' => UsimRole::count(),
            'permissions' => Permission::count(),
            'users' => User::count(),
            'languages' => UsimLanguage::count(),
            'unit_actors' => DB::table('usim_unit_actors')->count(),
            'model_has_roles' => DB::table($modelHasRolesTable)->count(),
        ];

        expect($countsAfterSecond)->toBe($countsAfterFirst)
            ->and(File::get(seedTempLangPath().'/en/unit.php'))->toBe($enUnitAfterFirst)
            ->and(File::get(seedTempLangPath().'/en/role.php'))->toBe($enRoleAfterFirst)
            ->and(File::get(seedTempLangPath().'/en/permission.php'))->toBe($enPermAfterFirst);
    });
});
