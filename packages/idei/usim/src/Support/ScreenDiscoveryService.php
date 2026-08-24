<?php

namespace Idei\Usim\Support;

use Idei\Usim\Screen;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Finder\SplFileInfo;
use Spatie\Permission\PermissionRegistrar;

class ScreenDiscoveryService
{
    /**
     * Scan the application for UI Screens and generate a manifest.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discover(): array
    {
        $rawScreensPath = config('usim.screens_path', app_path('UI/Screens'));
        $screensPath = \is_string($rawScreensPath) ? $rawScreensPath : app_path('UI/Screens');

        if (!\is_dir($screensPath)) {
            return [];
        }

        $manifest = [];
        $finder = new Finder();
        $finder->files()->in($screensPath)->name('*.php');

        $permissions = [];
        $permissionTranslationKeys = [];

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className && $this->isValidScreenClass($className)) {

                $id_offset = $this->generateStableOffset($className);
                $routePath = $className::getRoutePath();
                $resolvedPermissions = $className::resolvedPermissions();

                foreach ($resolvedPermissions as $permissionName => $translationKey) {
                    if (!\is_string($permissionName) || \trim($permissionName) === '') {
                        continue;
                    }

                    $permissionName = \trim($permissionName);
                    $permissions[] = $permissionName;

                    if (\is_string($translationKey) && \trim($translationKey) !== '') {
                        $permissionTranslationKeys[$permissionName] = \trim($translationKey);
                    }
                }

                $manifest[$className] = [
                    'id_offset' => $id_offset,
                    'route_path' => $routePath,
                ];
            }
        }

        $this->createOrUpdateSpatiePermissions($permissions);
        $this->upsertScreenPermissionTranslations($permissionTranslationKeys);

        return $manifest;
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createOrUpdateSpatiePermissions(array $permissions): void
    {
        if (!class_exists(Permission::class)) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = collect($permissions)
            ->filter(static fn($permission): bool => \trim($permission) !== '')
            ->map(static fn($permission): string => \trim((string) $permission))
            ->unique();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    /**
     * @param array<string, string> $permissionTranslationKeys
     */
    private function upsertScreenPermissionTranslations(array $permissionTranslationKeys): void
    {
        if ($permissionTranslationKeys === []) {
            return;
        }

        $screenPrefix = $this->normalizeTranslationPrefix(config('usim.i18n.i18n_key_prefixes.screen', 'screen.'));
        $screenPermissionPrefix = "{$screenPrefix}permissions.";

        foreach ($this->resolveTranslationLocales() as $locale) {
            $langDir = lang_path($locale);
            $langFile = "$langDir/permission.php";

            $payload = $this->loadLangArrayFile($langFile);

            foreach ($permissionTranslationKeys as $permission => $translationKey) {
                $translationKey = \trim($translationKey);
                $targetKey = $permission;

                if (\str_starts_with($translationKey, $screenPermissionPrefix)) {
                    $targetKey = Str::after($translationKey, $screenPermissionPrefix);
                }


                if (\trim($targetKey) === '') {
                    $targetKey = $permission;
                }

                // Keep existing user-defined translations untouched.
                if (Arr::has($payload, $targetKey)) {
                    continue;
                }

                Arr::set($payload, $targetKey, $this->buildPermissionTranslationLabel($permission));
            }

            if (!File::exists($langDir)) {
                File::makeDirectory($langDir, 0755, true);
            }

            File::put($langFile, "<?php\n\nreturn " . $this->exportPhpArrayShort($payload) . ";\n");
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveTranslationLocales(): array
    {
        $languages = config('usim.i18n.languages', []);
        $languages = \is_array($languages) ? $languages : [];

        $locales = [];
        foreach ($languages as $language) {
            if (!\is_array($language)) {
                continue;
            }

            $code = isset($language['code']) && \is_string($language['code']) ? trim($language['code']) : '';
            if ($code === '') {
                continue;
            }

            $isActive = \array_key_exists('active', $language) ? (bool) $language['active'] : true;
            if ($isActive) {
                $locales[] = $code;
            }
        }

        $fallback = config('usim.i18n.fallback_locale', config('app.fallback_locale', 'en'));
        if (\is_string($fallback) && trim($fallback) !== '') {
            $locales[] = trim($fallback);
        }

        $locales = array_values(array_unique($locales));

        return $locales !== [] ? $locales : ['en'];
    }

    private function normalizeTranslationPrefix(mixed $prefix): string
    {
        if (!\is_string($prefix) || trim($prefix) === '') {
            return '';
        }

        $prefix = trim($prefix);

        return \str_ends_with($prefix, '.') ? $prefix : $prefix . '.';
    }

    private function buildPermissionTranslationLabel(string $permission): string
    {
        $parts = array_values(array_filter(explode('.', $permission), static fn($part): bool => $part !== ''));
        if ($parts === []) {
            return Str::title(str_replace(['_', '.'], ' ', $permission));
        }

        $action = array_pop($parts);
        $screen = Str::title(str_replace(['_', '.'], ' ', implode(' ', $parts)));

        if ($action === 'access' && $screen !== '') {
            return "Permission to access {$screen}.";
        }

        $actionText = Str::title(str_replace('_', ' ', (string) $action));

        return trim("$actionText $screen");
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLangArrayFile(string $path): array
    {
        if (!\is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return \is_array($loaded) ? $loaded : [];
    }

    /**
     * @param array<mixed> $payload
     */
    private function exportPhpArrayShort(array $payload, int $indentLevel = 0): string
    {
        if ($payload === []) {
            return '[]';
        }

        $indent = \str_repeat('    ', $indentLevel);
        $itemIndent = \str_repeat('    ', $indentLevel + 1);

        $lines = ['['];

        foreach ($payload as $key => $value) {
            $serializedKey = \is_int($key) ? (string) $key : \var_export((string) $key, true);
            $serializedValue = \is_array($value)
                ? $this->exportPhpArrayShort($value, $indentLevel + 1)
                : \var_export($value, true);

            $lines[] = "{$itemIndent}{$serializedKey} => {$serializedValue},";
        }

        $lines[] = "$indent]";

        return implode("\n", $lines);
    }

    /**
     * Generate a stable, deterministic ID offset for a class.
     * Using CRC32 to generate a unique integer from the class name.
     * Multiplied by 10,000 to allow plenty of component IDs per screen.
     */
    private function generateStableOffset(string $className): int
    {
        // Use unsigned crc32 logic
        $hash = crc32($className);

        // Ensure positive integer (32-bit PHP compatibility)
        $hash = \sprintf("%u", $hash);

        // Take last 6 digits to keep numbers manageable but dispersed
        // This is a trade-off. Full CRC32 * 10000 might overflow max int on some systems.
        // Let's use a simpler approach:

        // Alternative: Use PHP's distinct integer for the string, mod MaxInt/limit
        // But we need to ensure no collisions.
        // 17 screens is small. CRC32 is fine.
        // Let's simple check strict unsigned.

        // We need a number that fits in an integer when multiplied by 10000
        // Max int is usually 2^63 (64 bit).

        // Let's rely on standard crc32 abs value.
        $val = abs((int) crc32($className));

        // Truncate to avoid overflow if multiplied by 10,000 if needed,
        // but wait, UIIdGenerator assumes offsets are spaced by 10,000.
        // If we simply pick the CRC32, two IDs might be close (e.g. 100000 and 100005).
        // The old logic used index * 10000.
        // We need discrete buckets.

        // Let's assume collisions are rare enough for now or use a persistent map logic?
        // No, the goal is stateless/deterministic.

        // Let's use the hash as the bucket ID.
        // Bucket ID = hash % 200000 (enough space for 200k screens?).
        // Offset = Bucket ID * 10000.
        // Hash collision likelyhood is low for small app.

        $bucket = $val % 100000;
        return $bucket * 10000;
    }

    private function getClassNameFromFile(SplFileInfo $file): string
    {
        // Simple extraction assuming PSR-4 structure inside App\UI\Screens
        // We can optimize this by token parsing if needed, but for now assumption works.
        $relativePath = $file->getRelativePathname();

        $namespace = config('usim.screens_namespace', 'App\\UI\\Screens');
        $namespace = is_string($namespace) ? $namespace : 'App\\UI\\Screens';
        $namespace = rtrim($namespace, '\\');

        $class = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

        return $class;
    }

    private function isValidScreenClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        return $reflection->isSubclassOf(Screen::class) && !$reflection->isAbstract();
    }
}
