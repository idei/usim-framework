<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\Config\UserConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class UsersSyncService
{
    protected UsimConfig $usimConfig;

    public function __construct(
        ?UsimConfig $usimConfig = null
    ) {
        $this->usimConfig = $usimConfig ?? app(UsimConfig::class);
    }

    /**
     * @return array{users_created: int, users_updated: int, users_deleted: int}
     */
    public function sync(): array
    {
        $stats = [
            'users_created' => 0,
            'users_updated' => 0,
            'users_deleted' => 0,
        ];

        DB::transaction(function () use (&$stats): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $rootUser = $this->usimConfig->rootUser;
            $userModelClass = $this->resolveUserModelImport();
            $guardName = $this->resolveGuardNameForUserModel($userModelClass);

            $this->upsertRootUser($stats, $rootUser, $userModelClass, $guardName);
            $this->upsertConfiguredUsers($stats, $userModelClass, $guardName);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    /**
     * @param array{users_created: int, users_updated: int, users_deleted: int} $stats
     */
    private function upsertRootUser(array &$stats, ?UserConfig $rootConfig, string $userModelClass, string $guardName): void
    {
        if ($rootConfig === null) {
            throw new \RuntimeException('ROOT_EMAIL must be a valid email to install USIM.');
        }

        if (!class_exists($userModelClass) || !is_subclass_of($userModelClass, Model::class)) {
            throw new \RuntimeException("Configured user model [{$userModelClass}] is invalid.");
        }

        $this->upsertSingleUser(
            stats: $stats,
            userModelClass: $userModelClass,
            key: 'root',
            userConfig: $rootConfig,
            fallbackRole: 'root',
            forceRootRules: true,
            guardName: $guardName
        );
    }

    /**
     * @param array{users_created: int, users_updated: int, users_deleted: int} $stats
     */
    private function upsertConfiguredUsers(array &$stats, string $userModelClass, string $guardName): void
    {
        if (!class_exists($userModelClass) || !is_subclass_of($userModelClass, Model::class)) {
            throw new \RuntimeException("Configured user model [{$userModelClass}] is invalid.");
        }

        foreach ($this->usimConfig->users as $key => $userConfig) {
            $key = (string) $key;
            if ($key === '' || $key === 'root' || $key === 'roles') {
                continue;
            }

            $this->upsertSingleUser(
                stats: $stats,
                userModelClass: $userModelClass,
                key: $key,
                userConfig: $userConfig,
                fallbackRole: $key,
                forceRootRules: false,
                guardName: $guardName
            );
        }
    }

    protected function resolveUserModelImport(): string
    {
        $configuredModel = $this->usimConfig->models['user'] ?? null;
        if (is_string($configuredModel) && $configuredModel !== '') {
            return $configuredModel;
        }

        // Check if the app has a custom User model location
        $authConfig = config('auth.providers.users.model', 'App\\Models\\User');

        return is_string($authConfig) && $authConfig !== ''
            ? $authConfig
            : 'App\\Models\\User';
    }

    private function resolveGuardNameForUserModel(string $userModelClass): string
    {
        $defaultGuard = $this->resolveAuthGuardName();

        if (!class_exists($userModelClass) || !is_subclass_of($userModelClass, Model::class)) {
            return $defaultGuard;
        }

        try {
            /** @var Model $user */
            $user = new $userModelClass();
            $guard = method_exists($user, 'getDefaultGuardName')
                ? $user->getDefaultGuardName()
                : $defaultGuard;

            return is_string($guard) && trim($guard) !== '' ? trim($guard) : $defaultGuard;
        } catch (\Throwable) {
            return $defaultGuard;
        }
    }

    private function resolveAuthGuardName(): string
    {
        $guard = config('auth.defaults.guard', 'web');

        return is_string($guard) && trim($guard) !== '' ? trim($guard) : 'web';
    }

    /**
     * @param array{users_created: int, users_updated: int, users_deleted: int} $stats
     * @param class-string<Model> $userModelClass
     * @param UserConfig $userConfig
     */
    private function upsertSingleUser(
        array &$stats,
        string $userModelClass,
        string $key,
        UserConfig $userConfig,
        string $fallbackRole,
        bool $forceRootRules,
        string $guardName
    ): void {
        $email = trim($userConfig->email);
        $password = trim($userConfig->password);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($forceRootRules) {
                throw new \RuntimeException('ROOT_EMAIL must be a valid email to install USIM.');
            }

            return;
        }

        if ($password === '' || strtoupper($password) === 'CHANGE_ME') {
            if ($forceRootRules) {
                throw new \RuntimeException('ROOT_PASSWORD must be set (not CHANGE_ME) to install USIM.');
            }

            return;
        }

        $firstName = trim($userConfig->firstName);
        $lastName = trim($userConfig->lastName);
        $name = trim("{$firstName} {$lastName}");

        /** @var Model|null $user */
        $user = $userModelClass::query()->where('email', $email)->first();
        $created = false;

        if ($user === null) {
            $user = new $userModelClass();
            $created = true;
            $user->setAttribute('email', $email);
            $user->setAttribute('remember_token', Str::random(10));
        }

        $user->setAttribute('name', $name !== '' ? $name : ucfirst($key));
        $user->setAttribute('password', bcrypt($password));

        if ($user->getAttribute('email_verified_at') === null) {
            $user->setAttribute('email_verified_at', now());
        }

        $user->save();

        if (!method_exists($user, 'syncRoles')) {
            throw new \RuntimeException('User model must use Spatie HasRoles trait to sync roles.');
        }

        $effectiveGuard = $this->resolveGuardNameForUser($user, $guardName);
        $unitRoles = $this->normalizeUserUnitRoles($userConfig, $fallbackRole);

        if (config('permission.teams')) {
            try {
                foreach ($unitRoles as $unitSlug => $roles) {
                    $unitModel = UsimUnit::firstOrCreate(['slug' => $unitSlug]);
                    setPermissionsTeamId($unitModel->id);

                    if (method_exists($user, 'usimUnits')) {
                        $user->usimUnits()->syncWithoutDetaching([$unitModel->id]);
                    } elseif (method_exists($user, 'units')) {
                        $user->units()->syncWithoutDetaching([$unitModel->id]);
                    }

                    if ($forceRootRules) {
                        UsimRole::findOrCreate('root', $effectiveGuard);
                        $user->syncRoles(['root']);
                    } else {
                        $resolvedRoles = $this->ensureRolesExist($roles, $effectiveGuard, $fallbackRole);
                        $user->syncRoles($resolvedRoles);
                    }
                }
            } finally {
                setPermissionsTeamId(null);
            }
        } else {
            $allRoles = [];
            foreach ($unitRoles as $roles) {
                $allRoles = array_merge($allRoles, $roles);
            }
            $allRoles = array_values(array_unique($allRoles));

            if ($forceRootRules) {
                UsimRole::findOrCreate('root', $effectiveGuard);
                $user->syncRoles(['root']);
            } else {
                $resolvedRoles = $this->ensureRolesExist($allRoles, $effectiveGuard, $fallbackRole);
                $user->syncRoles($resolvedRoles);
            }
        }

        if ($created) {
            $stats['users_created']++;
        } else {
            $stats['users_updated']++;
        }
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    private function ensureRolesExist(array $roles, string $guardName, string $fallbackRole = 'registered'): array
    {
        $normalized = [];
        foreach ($roles as $roleName) {
            $roleName = trim($roleName);
            if ($roleName !== '') {
                $normalized[] = $roleName;
            }
        }

        if ($normalized === []) {
            $normalized = [$fallbackRole];
        }

        $normalized = array_values(array_unique($normalized));

        foreach ($normalized as $roleName) {
            UsimRole::findOrCreate($roleName, $guardName);
        }

        return $normalized;
    }

    private function resolveGuardNameForUser(Model $user, string $fallbackGuard): string
    {
        try {
            $guard = method_exists($user, 'getDefaultGuardName')
                ? $user->getDefaultGuardName()
                : $fallbackGuard;

            return is_string($guard) && trim($guard) !== '' ? trim($guard) : $fallbackGuard;
        } catch (\Throwable) {
            return $fallbackGuard;
        }
    }

    /**
     * Normalizes the unit roles for a user.
     *
     * @return array<string, array<int, string>>
     */
    private function normalizeUserUnitRoles(UserConfig $userConfig, string $fallbackRole): array
    {
        $rawUnitRoles = $userConfig->unitRoles;

        if ($rawUnitRoles !== []) {
            $normalized = [];
            foreach ($rawUnitRoles as $unitSlug => $roles) {
                $cleanUnit = trim($unitSlug) !== '' ? trim($unitSlug) : 'main';
                $cleanRoles = [];
                foreach ($roles as $roleName) {
                    $trimmed = trim($roleName);
                    if ($trimmed !== '') {
                        $cleanRoles[] = $trimmed;
                    }
                }
                if ($cleanRoles === []) {
                    $cleanRoles = [$fallbackRole];
                }
                $normalized[$cleanUnit] = array_values(array_unique($cleanRoles));
            }

            return $normalized;
        }

        return ['main' => [$fallbackRole]];
    }
}
