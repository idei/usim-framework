<?php

namespace Idei\Usim\Support;

use Idei\Usim\Support\Config\DeviceConfig;
use Idei\Usim\Support\Config\I18nConfig;
use Idei\Usim\Support\Config\LanguageConfig;
use Idei\Usim\Support\Config\PermissionConfig;
use Idei\Usim\Support\Config\RoleConfig;
use Idei\Usim\Support\Config\UnitConfig;
use Idei\Usim\Support\Config\UserConfig;

final class UsimConfig
{
    // Escalares Base
    public private(set) string $appName;
    public private(set) string $apiUrl;
    public private(set) string $frontStoreKey;
    public private(set) string $screensNamespace;
    public private(set) string $screensPath;
    public private(set) string $uploadDisk;
    public private(set) bool $headlessMode;
    public private(set) string $defaultRegisteringRole;

    /** @var array<string, string> */
    public private(set) array $models = [];

    // Colecciones DTO
    /** @var array<string, RoleConfig> */
    public private(set) array $roles = [];

    /** @var array<string, UserConfig> */
    public private(set) array $users = [];

    /** @var UserConfig|null */
    public private(set) ?UserConfig $rootUser = null;

    /** @var array<string, DeviceConfig> */
    public private(set) array $devices = [];

    /** @var array<string, UnitConfig> */
    public private(set) array $units = [];

    /** @var array<string, PermissionConfig> */
    public private(set) array $permissions = [];

    /** @var I18nConfig */
    public private(set) I18nConfig $i18n;

    /** @var array<string, mixed> */
    private array $memoizationCache = [];

    public function __construct()
    {
        $raw = config('usim');
        if (!\is_array($raw)) {
            $raw = [];
        }

        // 1. Escalares Base
        $this->appName = \is_string($raw['app_name'] ?? null) ? $raw['app_name'] : 'USIM Framework';

        $fallbackUrl = config('app.url', 'http://localhost');
        $this->apiUrl = \is_string($raw['api_url'] ?? null) ? $raw['api_url'] : (\is_string($fallbackUrl) ? $fallbackUrl : 'http://localhost');

        $this->frontStoreKey = \is_string($raw['front_store_key'] ?? null) ? $raw['front_store_key'] : 'usim';
        $this->screensNamespace = \is_string($raw['screens_namespace'] ?? null) ? $raw['screens_namespace'] : 'App\\UI\\Screens';
        $this->screensPath = \is_string($raw['screens_path'] ?? null) ? $raw['screens_path'] : app_path('UI/Screens');
        $this->uploadDisk = \is_string($raw['upload_disk'] ?? null) ? $raw['upload_disk'] : 'local';
        $this->headlessMode = \is_bool($raw['headless_mode'] ?? null) ? $raw['headless_mode'] : false;
        $this->defaultRegisteringRole = \is_string($raw['default_registering_role'] ?? null) ? $raw['default_registering_role'] : 'registered';

        // 2. Modelos
        $rawModels = \is_array($raw['models'] ?? null) ? $raw['models'] : [];
        $this->models = [
            'user' => \is_string($rawModels['user'] ?? null) ? $rawModels['user'] : \App\Models\User::class,
            'device' => \is_string($rawModels['device'] ?? null) ? $rawModels['device'] : \App\Models\Device::class,
        ];

        // 3. Unidades (Units)
        $rawUnits = \is_array($raw['units'] ?? null) ? $raw['units'] : [];
        $rawStructure = \is_array($rawUnits['structure'] ?? null) ? $rawUnits['structure'] : [];
        foreach ($rawStructure as $slug => $meta) {
            if (\is_array($meta)) {
                /** @var array<string, array{display_name: string, description: string}> $parsedTranslations */
                $parsedTranslations = \is_array($meta['default_translations'] ?? null) ? $meta['default_translations'] : [];

                $this->units[$slug] = new UnitConfig(
                    parent: \is_string($meta['parent'] ?? null) ? $meta['parent'] : null,
                    type: \is_string($meta['type'] ?? null) ? $meta['type'] : null,
                    defaultTranslations: $parsedTranslations,
                );
            }
        }

        // 4. Permisos
        $rawPerms = \is_array($raw['permissions'] ?? null) ? $raw['permissions'] : [];
        foreach ($rawPerms as $slug => $meta) {
            if (\is_array($meta)) {
                /** @var array<string, array{display_name: string, description: string}> $parsedTranslations */
                $parsedTranslations = \is_array($meta['default_translations'] ?? null) ? $meta['default_translations'] : [];

                $this->permissions[$slug] = new PermissionConfig(
                    defaultTranslations: $parsedTranslations,
                );
            }
        }

        // 5. I18n
        $rawI18n = \is_array($raw['i18n'] ?? null) ? $raw['i18n'] : [];

        $languages = [];
        $rawLangs = \is_array($rawI18n['languages'] ?? null) ? $rawI18n['languages'] : [];
        foreach ($rawLangs as $lang) {
            if (\is_array($lang)) {
                $languages[] = new LanguageConfig(
                    code: \is_string($lang['code'] ?? null) ? $lang['code'] : '',
                    name: \is_string($lang['name'] ?? null) ? $lang['name'] : '',
                    nativeName: \is_string($lang['native_name'] ?? null) ? $lang['native_name'] : '',
                    active: \is_bool($lang['active'] ?? null) ? $lang['active'] : false,
                );
            }
        }

        $keyPrefixes = [];
        $rawPrefixes = \is_array($rawI18n['i18n_key_prefixes'] ?? null) ? $rawI18n['i18n_key_prefixes'] : [];
        foreach ($rawPrefixes as $key => $val) {
            if (\is_string($key) && \is_string($val)) {
                $keyPrefixes[$key] = $val;
            }
        }

        $this->i18n = new I18nConfig(
            defaultLocale: \is_string($rawI18n['default_locale'] ?? null) ? $rawI18n['default_locale'] : 'en',
            fallbackLocale: \is_string($rawI18n['fallback_locale'] ?? null) ? $rawI18n['fallback_locale'] : 'en',
            autoKeyMaxLength: \is_numeric($rawI18n['auto_key_max_length'] ?? null) ? (int)$rawI18n['auto_key_max_length'] : 30,
            logChannel: \is_string($rawI18n['log_channel'] ?? null) ? $rawI18n['log_channel'] : 'i18n',
            logAutokeySuggestions: \is_bool($rawI18n['log_autokey_suggestions'] ?? null) ? $rawI18n['log_autokey_suggestions'] : true,
            languages: $languages,
            keyPrefixes: $keyPrefixes,
        );

        // 6. Roles
        $rawRoles = \is_array($raw['roles'] ?? null) ? $raw['roles'] : [];
        foreach ($rawRoles as $name => $meta) {
            if (\is_array($meta)) {
                /** @var array<string, array{display_name: string, description: string}> $parsedTranslations */
                $parsedTranslations = \is_array($meta['default_translations'] ?? null) ? $meta['default_translations'] : [];

                /** @var array<int, string> $parsedPermissions */
                $parsedPermissions = \is_array($meta['permissions'] ?? null) ? $meta['permissions'] : [];

                $this->roles[$name] = new RoleConfig(
                    defaultTranslations: $parsedTranslations,
                    priority: \is_numeric($meta['priority'] ?? null) ? (int)$meta['priority'] : 10,
                    homeScreen: \is_string($meta['home_screen'] ?? null) ? $meta['home_screen'] : null,
                    permissions: $parsedPermissions,
                    guardName: \is_string($meta['guard_name'] ?? null) ? $meta['guard_name'] : 'web'
                );
            }
        }

        // 7. Usuarios
        $rawUsers = \is_array($raw['users'] ?? null) ? $raw['users'] : [];
        foreach ($rawUsers as $slug => $meta) {
            if (\is_array($meta)) {
                /** @var array<string, array<int, string>> $parsedUnitRoles */
                $parsedUnitRoles = \is_array($meta['unit_roles'] ?? null) ? $meta['unit_roles'] : [];

                $userConfig = new UserConfig(
                    firstName: \is_string($meta['first_name'] ?? null) ? $meta['first_name'] : '',
                    lastName: \is_string($meta['last_name'] ?? null) ? $meta['last_name'] : '',
                    email: \is_string($meta['email'] ?? null) ? $meta['email'] : '',
                    password: \is_string($meta['password'] ?? null) ? $meta['password'] : '',
                    unitRoles: $parsedUnitRoles,
                );

                if ($slug === 'root') {
                    $this->rootUser = $userConfig;
                    continue;
                }

                $this->users[$slug] = $userConfig;

            }
        }

        // 8. Dispositivos
        $rawDevices = \is_array($raw['devices'] ?? null) ? $raw['devices'] : [];
        foreach ($rawDevices as $slug => $meta) {
            if (\is_array($meta)) {
                /** @var array<string, mixed> $parsedSpecs */
                $parsedSpecs = \is_array($meta['specs'] ?? null) ? $meta['specs'] : [];

                /** @var array<string, array<int, string>> $parsedUnitRoles */
                $parsedUnitRoles = \is_array($meta['unit_roles'] ?? null) ? $meta['unit_roles'] : [];

                $this->devices[$slug] = new DeviceConfig(
                    name: \is_string($meta['name'] ?? null) ? $meta['name'] : '',
                    specs: $parsedSpecs,
                    unitRoles: $parsedUnitRoles,
                );
            }
        }
    }

    /** @var array<int, string> */
    public array $roleNames {
        get {
            /** @var array<int, string> $cached */
            $cached = $this->memoizationCache['roleNames'] ??= array_keys($this->roles);
            return $cached;
        }
    }

    /** @var array<string, RoleConfig> */
    public array $deviceRoles {
        get {
            /** @var array<string, RoleConfig> $cached */
            $cached = $this->memoizationCache['deviceRoles'] ??= array_filter(
                $this->roles,
                fn(RoleConfig $role) => $role->guardName === 'device'
            );
            return $cached;
        }
    }
}
