<?php

namespace Idei\Usim\Console\Commands\Support;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SeedAccessControl
{
    /**
     * @param array<string, string> $rootUserEnvValues
     * @return array{permissions_created:int,permissions_total:int,roles_created:int,roles_total:int,users_created:int,users_updated:int,languages_created:int,languages_updated:int}
     */
    public function seed(array $rootUserEnvValues, string $userModelClass, ?callable $line = null): array
    {
        /** @var array{permissions_created:int, permissions_total:int, roles_created:int, roles_total:int, users_created:int, users_updated:int, languages_created:int, languages_updated:int} $stats */
        $stats = [
            'permissions_created' => 0,
            'permissions_total' => 0,
            'roles_created' => 0,
            'roles_total' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'languages_created' => 0,
            'languages_updated' => 0,
        ];

        DB::transaction(function () use (&$stats, $rootUserEnvValues, $userModelClass, $line): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $guardName = $this->resolveGuardNameForUserModel($userModelClass);

            // 1. Sincronización de PERMISOS
            $usimConfig = $this->loadUsimConfig();
            $permissionConfig = $usimConfig['permissions'] ?? config('usim.permissions', []);
            if (!is_array($permissionConfig)) {
                $permissionConfig = [];
            }

            $permissions = $this->collectPermissionNames();
            $stats['permissions_total'] = count($permissions);

            foreach ($permissions as $permissionName) {
                $permission = Permission::query()
                    ->where('name', $permissionName)
                    ->where('guard_name', $guardName)
                    ->first();

                if ($permission === null) {
                    // Pasamos el valor directo (sea array o string), Permission se encargará de parsearlo
                    $configValue = $permissionConfig[$permissionName] ?? "Permission for $permissionName";

                    Permission::create(
                        ['name' => $permissionName, 'guard_name' => $guardName],
                    );
                    $stats['permissions_created']++;
                }
            }

            // 2. Sincronización de ROLES (con Screen Home y Priority)
            $roles = $this->normalizeRolesConfig();
            $stats['roles_total'] = count($roles);

            foreach ($roles as $roleName => $roleMeta) {
                $isRootRole = $roleName === 'root';
                if (is_callable($line)) {
                    $line("  ↳ Reviewing role [{$roleName}]...");
                }

                // Buscamos usando el modelo extendido de USIM
                $role = UsimRole::query()
                    ->where('name', $roleName)
                    ->where('guard_name', $guardName)
                    ->first();

                if ($role === null) {
                    // Extraemos la home_screen y prioridad definidas en el config de USIM
                    $homeScreenValue = $roleMeta['home_screen'] ?? 'welcome';
                    $priorityValue = $roleMeta['priority'] ?? 100;
                    $homeScreen = is_string($homeScreenValue) && trim($homeScreenValue) !== ''
                        ? trim($homeScreenValue)
                        : 'welcome';
                    if (is_int($priorityValue)) {
                        $priority = $priorityValue;
                    } elseif (is_string($priorityValue) || is_float($priorityValue)) {
                        $priority = (int) $priorityValue;
                    } else {
                        $priority = 100;
                    }

                    // ⚡ Creamos el Rol inyectando su settings automáticamente
                    $role = UsimRole::createWithHome(
                        name: $roleName,
                        homeScreenSlug: $homeScreen,
                        priority: $priority,
                        guardName: $guardName
                    );
                    $stats['roles_created']++;

                    if (is_callable($line)) {
                        $line("    ✓ Role [{$roleName}] created for guard [{$guardName}] (Home: {$homeScreen}, Priority: {$priority})");
                    }
                } elseif (is_callable($line)) {
                    $line("    → Role [{$roleName}] already exists for guard [{$guardName}]");
                }

                /** @var UsimRole $role */
                $rolePermissions = $this->normalizeRolePermissions($roleMeta, $permissions, $isRootRole);
                $role->syncPermissions($rolePermissions);

                if (is_callable($line)) {
                    $line("    ✓ Role [{$roleName}] permissions synced: " . implode(', ', $rolePermissions));
                }
            }

            $this->upsertUnits($stats, $line);
            $this->upsertRootUser($stats, $rootUserEnvValues, $userModelClass, $guardName);
            $this->upsertConfiguredUsers($stats, $userModelClass, $guardName);
            $this->upsertLanguages($stats);
            $this->upsertRoleAndPermissionTranslations($line);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->normalizeSeedStats($stats);
    }

    /**
     * @return array<int, string>
     */
    private function collectPermissionNames(): array
    {
        $usimConfig = $this->loadUsimConfig();
        $permissionConfig = $usimConfig['permissions'] ?? config('usim.permissions', []);
        $defined = [];

        if (is_array($permissionConfig)) {
            foreach (array_keys($permissionConfig) as $permissionName) {
                if (is_string($permissionName) && trim($permissionName) !== '') {
                    $defined[] = trim($permissionName);
                }
            }
        }

        $rolePermissions = [];
        foreach ($this->normalizeRolesConfig() as $roleMeta) {
            $configured = $roleMeta['permissions'] ?? [];
            $configured = is_array($configured) ? $configured : [];

            foreach ($configured as $permissionName) {
                if (!is_string($permissionName)) {
                    continue;
                }

                $permissionName = trim($permissionName);
                if ($permissionName === '') {
                    continue;
                }

                $rolePermissions[] = $permissionName;
            }
        }

        $all = array_values(array_unique([...$defined, ...$rolePermissions]));
        sort($all);

        return $all;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeRolesConfig(): array
    {
        $usimConfig = $this->loadUsimConfig();
        $roles = $usimConfig['roles'] ?? config('usim.roles', []);
        if (!is_array($roles)) {
            $roles = [];
        }

        $normalizedRoles = [];
        foreach ($roles as $roleName => $roleMeta) {
            if (!is_string($roleName) || trim($roleName) === '' || !is_array($roleMeta)) {
                continue;
            }

            $normalizedRoles[$roleName] = $roleMeta;
        }

        // if (!array_key_exists('root', $normalizedRoles)) {
        //     $normalizedRoles['root'] = ['permissions' => ['*']];
        // }

        // $normalizedRoles['root']['permissions'] = ['*'];

        /** @var array<string, array<string, mixed>> $normalizedRoles */
        return $normalizedRoles;
    }

    /**
     * @param array<string, mixed> $roleMeta
     * @param array<int, string> $allPermissions
     * @return array<int, string>
     */
    private function normalizeRolePermissions(array $roleMeta, array $allPermissions, bool $isRootRole): array
    {
        if ($isRootRole) {
            return $allPermissions;
        }

        $permissionsValue = $roleMeta['permissions'] ?? [];
        /** @var array<int, mixed> $permissions */
        $permissions = is_array($permissionsValue) ? $permissionsValue : [];

        $normalized = [];
        foreach ($permissions as $permissionName) {
            if (!is_string($permissionName)) {
                continue;
            }

            $permissionName = trim($permissionName);
            if ($permissionName === '') {
                continue;
            }

            $normalized[] = $permissionName;
        }

        if (in_array('*', $normalized, true)) {
            return $allPermissions;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, int> $stats
     */
    private function upsertUnits(array &$stats, ?callable $line = null): void
    {
        if (!config('permission.teams')) {
            return;
        }

        $usimConfig = $this->loadUsimConfig();
        $unitsConfig = $usimConfig['units'] ?? config('usim.units', []);
        $structure = $unitsConfig['structure'] ?? [];

        if (!is_array($structure) || empty($structure)) {
            return;
        }

        if (is_callable($line)) {
            $line('  ↳ Synchronizing organizational units...');
        }

        // 1. Crear o actualizar unidades básicas
        foreach ($structure as $slug => $data) {
            if (!is_string($slug) || trim($slug) === '') {
                continue;
            }

            $type = is_array($data) && isset($data['type']) && is_string($data['type'])
                ? $data['type']
                : null;

            UsimUnit::updateOrCreate(
                ['slug' => $slug],
                ['type' => $type]
            );
        }

        // 2. Resolver jerarquía padre-hijo
        foreach ($structure as $slug => $data) {
            if (!is_string($slug) || !is_array($data)) {
                continue;
            }

            $parentId = null;
            if (!empty($data['parent']) && is_string($data['parent'])) {
                $parentId = UsimUnit::where('slug', $data['parent'])->value('id');
            }

            UsimUnit::where('slug', $slug)->update(['parent_id' => $parentId]);
        }

        // 3. Sincronizar traducciones de unidades en los archivos de idioma
        foreach ($structure as $slug => $data) {
            if (!is_string($slug) || !is_array($data)) {
                continue;
            }

            $translations = $this->normalizeDefaultTranslations($data['default_translations'] ?? null);
            foreach ($translations as $locale => $meta) {
                if (isset($meta['display_name'])) {
                    $this->upsertLangValueByKey(
                        $locale,
                        "unit.{$slug}.display_name",
                        $meta['display_name']
                    );
                }
                if (isset($meta['description'])) {
                    $this->upsertLangValueByKey(
                        $locale,
                        "unit.{$slug}.description",
                        $meta['description']
                    );
                }
            }
        }

        if (is_callable($line)) {
            $line('    ✓ Organizational units synchronized successfully.');
        }
    }

    /**
     * @param array<string, int> $stats
     * @param array<string, string> $rootUserEnvValues
     */
    private function upsertRootUser(array &$stats, array $rootUserEnvValues, string $userModelClass, string $guardName): void
    {
        if (!class_exists($userModelClass) || !is_subclass_of($userModelClass, Model::class)) {
            throw new \RuntimeException("Configured user model [{$userModelClass}] is invalid.");
        }

        $usimConfig = $this->loadUsimConfig();
        $usersConfig = $usimConfig['users'] ?? config('usim.users', []);
        $usersConfig = \is_array($usersConfig) ? $usersConfig : [];

        /** @var array<string, mixed> $rootConfig */
        $rootConfig = \is_array($usersConfig['root'] ?? null) ? $usersConfig['root'] : [];
        $rootValues = $rootUserEnvValues !== []
            ? $rootUserEnvValues
            : [
                'first_name' => $this->normalizeStringConfigValue($rootConfig['first_name'] ?? config('usim.users.root.first_name', 'Root'), 'Root'),
                'last_name' => $this->normalizeStringConfigValue($rootConfig['last_name'] ?? config('usim.users.root.last_name', 'User'), 'User'),
                'email' => $this->normalizeStringConfigValue($rootConfig['email'] ?? config('usim.users.root.email', ''), ''),
                'password' => $this->normalizeStringConfigValue($rootConfig['password'] ?? config('usim.users.root.password', ''), ''),
                'unit' => $this->normalizeStringConfigValue($rootConfig['unit'] ?? config('usim.users.root.unit', config('usim.units.default', 'main')), 'main'),
            ];

        $rootEnv = [
            'first_name' => (string) ($rootValues['first_name'] ?? 'Root'),
            'last_name' => (string) ($rootValues['last_name'] ?? 'User'),
            'email' => (string) ($rootValues['email'] ?? ''),
            'password' => (string) ($rootValues['password'] ?? ''),
            'unit' => (string) ($rootValues['unit'] ?? ($rootConfig['unit'] ?? config('usim.users.root.unit', config('usim.units.default', 'main')))),
        ];

        $this->upsertSingleUser(
            stats: $stats,
            userModelClass: $userModelClass,
            key: 'root',
            userConfig: $rootEnv,
            fallbackRole: 'root',
            forceRootRules: true,
            guardName: $guardName
        );
    }

    /**
     * @param array<string, int> $stats
     */
    private function upsertConfiguredUsers(array &$stats, string $userModelClass, string $guardName): void
    {
        if (!class_exists($userModelClass) || !is_subclass_of($userModelClass, Model::class)) {
            throw new \RuntimeException("Configured user model [{$userModelClass}] is invalid.");
        }

        $usimConfig = $this->loadUsimConfig();
        $usersConfig = $usimConfig['users'] ?? config('usim.users', []);
        $usersConfig = is_array($usersConfig) ? $usersConfig : [];

        /** @var array<string, array<string, mixed>> $usersConfig */
        foreach ($usersConfig as $key => $userConfig) {
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

    /**
     * @param array<string, int> $stats
     * @param class-string<Model> $userModelClass
     * @param array<string, mixed> $userConfig
     */
    private function upsertSingleUser(
        array &$stats,
        string $userModelClass,
        string $key,
        array $userConfig,
        string $fallbackRole,
        bool $forceRootRules,
        string $guardName
    ): void {
        $email = isset($userConfig['email']) && is_string($userConfig['email']) ? trim($userConfig['email']) : '';
        $password = isset($userConfig['password']) && is_string($userConfig['password']) ? trim($userConfig['password']) : '';

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

        $firstName = isset($userConfig['first_name']) && is_string($userConfig['first_name'])
            ? trim($userConfig['first_name'])
            : ucfirst($key);
        $lastName = isset($userConfig['last_name']) && is_string($userConfig['last_name'])
            ? trim($userConfig['last_name'])
            : 'User';
        $name = trim($firstName . ' ' . $lastName);

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

        if (config('permission.teams')) {
            $unitSlug = isset($userConfig['unit']) && is_string($userConfig['unit']) && trim($userConfig['unit']) !== ''
                ? trim($userConfig['unit'])
                : config('usim.units.default', 'main');

            $unitModel = UsimUnit::firstOrCreate(['slug' => $unitSlug]);
            setPermissionsTeamId($unitModel->id);

            if (method_exists($user, 'usimUnits')) {
                $user->usimUnits()->syncWithoutDetaching([$unitModel->id]);
            } elseif (method_exists($user, 'units')) {
                $user->units()->syncWithoutDetaching([$unitModel->id]);
            }
        }

        if (!method_exists($user, 'syncRoles')) {
            throw new \RuntimeException('User model must use Spatie HasRoles trait to sync roles.');
        }

        $effectiveGuard = $this->resolveGuardNameForUser($user, $guardName);

        if ($forceRootRules) {
            UsimRole::findOrCreate('root', $effectiveGuard);
            $user->syncRoles(['root']);
        } else {
            $roles = $this->normalizeUserRoles($userConfig, $fallbackRole, $effectiveGuard);
            $user->syncRoles($roles);
        }

        if ($created) {
            $stats['users_created']++;
        } else {
            $stats['users_updated']++;
        }
    }

    /**
     * @param array<string, mixed> $userConfig
     * @return array<int, string>
     */
    private function normalizeUserRoles(array $userConfig, string $fallbackRole, string $guardName): array
    {
        $roles = $userConfig['roles'] ?? [$fallbackRole];

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!is_array($roles) || $roles === []) {
            $roles = [$fallbackRole];
        }

        $normalized = [];
        foreach ($roles as $roleName) {
            if (!is_string($roleName)) {
                continue;
            }

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
            $exists = UsimRole::query()
                ->where('name', $roleName)
                ->where('guard_name', $guardName)
                ->exists();

            if (!$exists) {
                // Esto asegura que pase por tu pipeline limpio de UsimRole
                UsimRole::createWithHome(
                    name: $roleName,
                    homeScreenSlug: 'welcome',
                    priority: 100,
                    guardName: $guardName
                );
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, int> $stats
     */
    private function upsertLanguages(array &$stats): void
    {
        $usimConfig = $this->loadUsimConfig();
        $i18nConfig = $usimConfig['i18n'] ?? [];
        if (!is_array($i18nConfig)) {
            $i18nConfig = [];
        }

        $configuredLanguages = $i18nConfig['languages'] ?? config('usim.i18n.languages', []);
        if (!is_array($configuredLanguages)) {
            $configuredLanguages = [];
        }

        $fallbackCode = $i18nConfig['fallback_locale'] ?? config('usim.i18n.fallback_locale', config('app.fallback_locale', 'en'));
        $fallbackCode = is_string($fallbackCode) && trim($fallbackCode) !== '' ? trim($fallbackCode) : 'en';

        $touchedFallback = false;

        foreach ($configuredLanguages as $languageConfig) {
            if (!is_array($languageConfig)) {
                continue;
            }

            $code = isset($languageConfig['code']) && is_string($languageConfig['code']) ? trim($languageConfig['code']) : '';
            if ($code === '') {
                continue;
            }

            $name = isset($languageConfig['name']) && is_string($languageConfig['name'])
                ? trim($languageConfig['name'])
                : strtoupper($code);
            $nativeName = isset($languageConfig['native_name']) && is_string($languageConfig['native_name'])
                ? trim($languageConfig['native_name'])
                : null;
            $isActive = array_key_exists('active', $languageConfig)
                ? (bool) $languageConfig['active']
                : true;
            $isFallback = $code === $fallbackCode;

            $language = UsimLanguage::query()->where('code', $code)->first();
            $created = false;

            if ($language === null) {
                $language = new UsimLanguage();
                $language->code = $code;
                $created = true;
            }

            $language->name = $name !== '' ? $name : strtoupper($code);
            $language->native_name = $nativeName;
            $language->is_active = $isActive;
            $language->is_fallback = $isFallback;
            $language->save();

            if ($isFallback) {
                $touchedFallback = true;
            }

            if ($created) {
                $stats['languages_created']++;
            } else {
                $stats['languages_updated']++;
            }
        }

        if (!$touchedFallback) {
            $fallbackLanguage = UsimLanguage::query()->firstOrNew(['code' => $fallbackCode]);
            $created = !$fallbackLanguage->exists;
            $fallbackLanguage->name = $fallbackLanguage->name ?: strtoupper($fallbackCode);
            $fallbackLanguage->native_name = $fallbackLanguage->native_name ?: strtoupper($fallbackCode);
            $fallbackLanguage->is_active = true;
            $fallbackLanguage->is_fallback = true;
            $fallbackLanguage->save();

            if ($created) {
                $stats['languages_created']++;
            } else {
                $stats['languages_updated']++;
            }
        }

        UsimLanguage::query()
            ->where('code', '!=', $fallbackCode)
            ->update(['is_fallback' => false]);
    }

    private function upsertRoleAndPermissionTranslations(?callable $line = null): void
    {
        $usimConfig = $this->loadUsimConfig();
        $i18nConfig = $usimConfig['i18n'] ?? [];
        if (!is_array($i18nConfig)) {
            $i18nConfig = [];
        }

        $prefixes = $i18nConfig['i18n_key_prefixes'] ?? config('usim.i18n.i18n_key_prefixes', []);
        $prefixes = is_array($prefixes) ? $prefixes : [];

        $rolePrefix = $this->normalizeTranslationPrefix($prefixes['role'] ?? 'role.');
        $permissionPrefix = $this->normalizeTranslationPrefix($prefixes['permission'] ?? 'permission.');

        $roles = $this->normalizeRolesConfig();
        foreach ($roles as $roleName => $roleMeta) {
            $translations = $this->normalizeDefaultTranslations($roleMeta['default_translations'] ?? null);

            foreach ($translations as $locale => $meta) {
                $this->upsertLangValueByKey(
                    $locale,
                    $rolePrefix . $roleName . '.name',
                    $meta['display_name'] ?? $roleName
                );
                $this->upsertLangValueByKey(
                    $locale,
                    $rolePrefix . $roleName . '.description',
                    $meta['description'] ?? ''
                );
            }
        }

        $permissions = $usimConfig['permissions'] ?? config('usim.permissions', []);
        $permissions = is_array($permissions) ? $permissions : [];

        foreach ($permissions as $permissionName => $permissionMeta) {
            if (!is_string($permissionName) || trim($permissionName) === '') {
                continue;
            }

            $permissionName = trim($permissionName);
            $permissionMeta = is_array($permissionMeta) ? $permissionMeta : [];
            $translations = $this->normalizeDefaultTranslations($permissionMeta['default_translations'] ?? null);

            foreach ($translations as $locale => $meta) {
                $this->upsertLangValueByKey(
                    $locale,
                    $permissionPrefix . $permissionName . '.name',
                    $meta['display_name'] ?? $permissionName
                );
                $this->upsertLangValueByKey(
                    $locale,
                    $permissionPrefix . $permissionName . '.description',
                    $meta['description'] ?? ''
                );
            }
        }

        if (is_callable($line)) {
            $line('  ↳ Role and permission default translations synced into lang files.');
        }
    }

    /**
     * @param mixed $translations
     * @return array<string, array{display_name?: string, description?: string}>
     */
    private function normalizeDefaultTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $locale => $meta) {
            if (!is_string($locale) || trim($locale) === '' || !is_array($meta)) {
                continue;
            }

            $locale = trim($locale);
            $normalized[$locale] = [];

            if (isset($meta['display_name']) && is_string($meta['display_name'])) {
                $normalized[$locale]['display_name'] = $meta['display_name'];
            }

            if (isset($meta['description']) && is_string($meta['description'])) {
                $normalized[$locale]['description'] = $meta['description'];
            }
        }

        return $normalized;
    }

    private function normalizeTranslationPrefix(mixed $prefix): string
    {
        if (!is_string($prefix) || trim($prefix) === '') {
            return '';
        }

        $prefix = trim($prefix);

        return str_ends_with($prefix, '.') ? $prefix : $prefix . '.';
    }

    /**
     * @param array<string, int> $stats
     * @return array{permissions_created:int, permissions_total:int, roles_created:int, roles_total:int, users_created:int, users_updated:int, languages_created:int, languages_updated:int}
     */
    private function normalizeSeedStats(array $stats): array
    {
        return [
            'permissions_created' => (int) ($stats['permissions_created'] ?? 0),
            'permissions_total' => (int) ($stats['permissions_total'] ?? 0),
            'roles_created' => (int) ($stats['roles_created'] ?? 0),
            'roles_total' => (int) ($stats['roles_total'] ?? 0),
            'users_created' => (int) ($stats['users_created'] ?? 0),
            'users_updated' => (int) ($stats['users_updated'] ?? 0),
            'languages_created' => (int) ($stats['languages_created'] ?? 0),
            'languages_updated' => (int) ($stats['languages_updated'] ?? 0),
        ];
    }

    private function normalizeStringConfigValue(mixed $value, string $default): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    private function upsertLangValueByKey(string $locale, string $translationKey, string $value): void
    {
        $locale = trim($locale);
        $translationKey = trim($translationKey);

        if ($locale === '' || $translationKey === '') {
            return;
        }

        $segments = array_values(array_filter(explode('.', $translationKey), static fn($segment): bool => $segment !== ''));
        if (count($segments) < 2) {
            return;
        }

        $file = array_shift($segments);
        $langDir = lang_path($locale);
        $langFile = $langDir . '/' . $file . '.php';

        $payload = $this->loadLangArrayFile($langFile);
        Arr::set($payload, implode('.', $segments), $value);

        if (!File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . $this->exportPhpArrayShort($payload) . ";\n";
        File::put($langFile, $content);
    }

    /**
     * @param array<mixed> $payload
     */
    private function exportPhpArrayShort(array $payload, int $indentLevel = 0): string
    {
        if ($payload === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $indentLevel);
        $itemIndent = str_repeat('    ', $indentLevel + 1);

        $lines = ['['];

        foreach ($payload as $key => $value) {
            $serializedKey = is_int($key) ? (string) $key : var_export((string) $key, true);
            $serializedValue = is_array($value)
                ? $this->exportPhpArrayShort($value, $indentLevel + 1)
                : var_export($value, true);

            $lines[] = $itemIndent . $serializedKey . ' => ' . $serializedValue . ',';
        }

        $lines[] = $indent . ']';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLangArrayFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return is_array($loaded) ? $loaded : [];
    }

    private function resolveAuthGuardName(): string
    {
        $guard = config('auth.defaults.guard', 'web');

        return is_string($guard) && trim($guard) !== '' ? trim($guard) : 'web';
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
     * @return array<string, mixed>
     */
    private function loadUsimConfig(): array
    {
        $packageConfig = $this->loadConfigFile(dirname(__DIR__, 4) . '/config/usim.php');
        $publishedConfig = $this->loadConfigFile(config_path('usim.php'));

        $merged = array_replace_recursive($packageConfig, $publishedConfig);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfigFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return \is_array($loaded) ? $loaded : [];
    }
}
