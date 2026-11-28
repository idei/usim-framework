<?php

declare(strict_types=1);

namespace Idei\Usim\Support;

use App\Models\Device;
use App\Models\User;
use Idei\Usim\Screen;
use Idei\Usim\Support\Config\ConfigReader;
use Idei\Usim\Support\Config\DeviceConfig;
use Idei\Usim\Support\Config\I18nConfig;
use Idei\Usim\Support\Config\LanguageConfig;
use Idei\Usim\Support\Config\PermissionConfig;
use Idei\Usim\Support\Config\RoleConfig;
use Idei\Usim\Support\Config\UnitConfig;
use Idei\Usim\Support\Config\UserConfig;
use Illuminate\Support\Str;

final class UsimConfig
{
    // Escalares Base
    public private(set) string $appName;

    public private(set) string $apiUrl;

    public private(set) string $frontStoreKey;

    public private(set) string $screensNamespace;

    public private(set) string $screensPath;

    public private(set) string $requestsNamespace;

    public private(set) string $requestsPath;

    public private(set) string $uploadDisk;

    public private(set) bool $headlessMode;

    public private(set) string $defaultRegisteringRole;

    public private(set) string $defaultLayout;

    public private(set) ?string $guestLayout = null;

    public private(set) string $defaultMenuScreen;

    public private(set) ?string $defaultSidebarMenuScreen = null;

    public private(set) ?string $defaultStatusBarScreen = null;

    public private(set) int $uiCacheTtl;

    /** @var array<string, string> */
    public private(set) array $models = [];

    // Colecciones DTO
    /** @var array<string, RoleConfig> */
    public private(set) array $roles = [];

    /** @var array<string, UserConfig> */
    public private(set) array $users = [];

    public private(set) ?UserConfig $rootUser = null;

    /** @var array<string, DeviceConfig> */
    public private(set) array $devices = [];

    /** @var array<string, UnitConfig> */
    public private(set) array $units = [];

    /** @var array<string, PermissionConfig> */
    public private(set) array $permissions = [];

    public private(set) I18nConfig $i18n;

    /** @var array<string, mixed> */
    private array $memoizationCache = [];

    public function __construct()
    {
        $config = ConfigReader::fromMixed(config('usim'));

        $this->loadBaseScalars($config);
        $this->models = $this->parseModels($config->section('models'));
        $this->units = ConfigReader::mapEntries($config->section('units')->array('structure'), UnitConfig::fromArray(...));
        $this->permissions = ConfigReader::mapEntries($config->array('permissions'), PermissionConfig::fromArray(...));
        $this->i18n = I18nConfig::fromArray($config->array('i18n'));
        $this->roles = ConfigReader::mapEntries($config->array('roles'), RoleConfig::fromArray(...));
        $this->loadUsers($config->array('users'));
        $this->devices = ConfigReader::mapEntries($config->array('devices'), DeviceConfig::fromArray(...));
    }

    private function loadBaseScalars(ConfigReader $config): void
    {
        $defaultApiUrl = ConfigReader::stringValue(config('app.url', 'http://localhost'), 'http://localhost');

        $this->appName = $config->string('app_name', 'USIM Framework');
        $this->apiUrl = $config->string('api_url', $defaultApiUrl);
        $this->frontStoreKey = $config->string('front_store_key', 'usim');
        $this->screensNamespace = $config->string('screens_namespace', 'App\\UI\\Screens');
        $this->screensPath = $config->string('screens_path', app_path('UI/Screens'));
        $this->requestsNamespace = $config->string('requests_namespace', 'App\\UI\\Requests');
        $this->requestsPath = $config->string('requests_path', app_path('UI/Requests'));
        $this->uploadDisk = $config->string('upload_disk', 'local');
        $this->headlessMode = $config->bool('headless_mode', false);
        $this->defaultRegisteringRole = $config->string('default_registering_role', 'registered');
        $this->defaultLayout = $config->string('default_layout', 'App\\UI\\Layouts\\WorkbenchLayout');
        $this->guestLayout = $config->nonEmptyString('guest_layout');
        $this->defaultMenuScreen = $config->string('default_menu_screen', 'App\\UI\\Screens\\Menu');
        $this->defaultSidebarMenuScreen = $config->nonEmptyString('default_sidebar_menu_screen');
        $this->defaultStatusBarScreen = $config->nonEmptyString('default_status_bar_screen');
        $this->uiCacheTtl = $config->int('ui_cache_ttl', 7200);
    }

    /**
     * @return array<string, string>
     */
    private function parseModels(ConfigReader $models): array
    {
        return [
            'user' => $models->string('user', User::class),
            'device' => $models->string('device', Device::class),
        ];
    }

    /**
     * @param  array<mixed, mixed>  $rawUsers
     */
    private function loadUsers(array $rawUsers): void
    {
        $parsedUsers = ConfigReader::mapEntries($rawUsers, UserConfig::fromArray(...));

        if (isset($parsedUsers['root'])) {
            $this->rootUser = $parsedUsers['root'];
            unset($parsedUsers['root']);
        }

        $this->users = $parsedUsers;
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
                fn (RoleConfig $role) => $role->guardName === 'device'
            );

            return $cached;
        }
    }

    /** @var array<int, string> */
    public array $activeLanguageCodes {
        get {
            /** @var array<int, string> $cached */
            $cached = $this->memoizationCache['activeLanguageCodes'] ??= array_values(array_unique(array_filter(
                array_map(
                    static fn (LanguageConfig $lang): string => $lang->active ? trim($lang->code) : '',
                    $this->i18n->languages
                ),
                static fn (string $code): bool => $code !== ''
            )));

            return $cached;
        }
    }

    /**
     * Resolve a screen class from a route slug (e.g. 'admin/users-manager' -> 'App\UI\Screens\Admin\UsersManager').
     *
     * @return class-string<Screen>|null
     */
    public function resolveScreenClass(string $screenRoute): ?string
    {
        $screenNameSegments = collect(explode('/', trim($screenRoute, '/')))
            ->map(fn (string $segment) => Str::studly($segment))
            ->join('\\');

        $screenClass = "{$this->screensNamespace}\\{$screenNameSegments}";

        if (class_exists($screenClass) && is_subclass_of($screenClass, Screen::class)) {
            /** @var class-string<Screen> $screenClass */
            return $screenClass;
        }

        return null;
    }

    /**
     * Resolve a route slug from a screen class.
     */
    public function resolveScreenSlug(string $screenClass): string
    {
        if (class_exists($screenClass) && method_exists($screenClass, 'getRoutePath')) {
            return ltrim($screenClass::getRoutePath(), '/');
        }

        if (str_starts_with($screenClass, $this->screensNamespace)) {
            $relative = substr($screenClass, strlen($this->screensNamespace));
            $segments = explode('\\', trim($relative, '\\'));
            $urlSegments = array_map(fn ($s) => Str::kebab($s), $segments);

            return implode('/', $urlSegments);
        }

        return Str::kebab(class_basename($screenClass));
    }
}
