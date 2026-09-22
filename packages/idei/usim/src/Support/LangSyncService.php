<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimLanguage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LangSyncService
{
    public function __construct(protected UsimConfig $usimConfig, protected ?string $baseLangPath = null) {}

    /**
     * Synchronizes the languages and role/permission translations configured in the USIM configuration.
     *
     * @return array{languages_created: int, languages_updated: int}
     */
    public function sync(): array
    {
        $stats = [
            'languages_created' => 0,
            'languages_updated' => 0,
        ];

        DB::transaction(function () use (&$stats): void {
            $this->upsertLanguages($stats);
            $this->upsertRoleAndPermissionTranslations();
        });

        return $stats;
    }

    /**
     * @param array{languages_created: int, languages_updated: int} $stats
     */
    private function upsertLanguages(array &$stats): void
    {
        $i18nConfig = $this->usimConfig->i18n;
        $configuredLanguages = $i18nConfig->languages;

        $fallbackCode = trim($i18nConfig->fallbackLocale);
        if ($fallbackCode === '') {
            $appFallback = config('app.fallback_locale', 'en');
            $fallbackCode = \is_string($appFallback) && trim($appFallback) !== '' ? trim($appFallback) : 'en';
        }

        $touchedFallback = false;

        foreach ($configuredLanguages as $languageConfig) {
            $code = trim($languageConfig->code);
            if ($code === '') {
                continue;
            }

            $name = trim($languageConfig->name);
            $name = $name !== '' ? $name : strtoupper($code);

            $nativeName = trim($languageConfig->nativeName);
            $nativeName = $nativeName !== '' ? $nativeName : null;

            $isActive = $languageConfig->active;
            $isFallback = $code === $fallbackCode;

            $language = UsimLanguage::query()->where('code', $code)->first();
            $created = false;

            if ($language === null) {
                $language = new UsimLanguage();
                $language->code = $code;
                $created = true;
            }

            $language->name = $name;
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

    private function upsertRoleAndPermissionTranslations(): void
    {
        $prefixes = $this->usimConfig->i18n->keyPrefixes;

        $rolePrefix = $this->normalizeTranslationPrefix($prefixes['role'] ?? 'role.');
        $permissionPrefix = $this->normalizeTranslationPrefix($prefixes['permission'] ?? 'permission.');

        foreach ($this->usimConfig->roles as $roleName => $roleConfig) {
            $roleName = trim((string) $roleName);
            if ($roleName === '') {
                continue;
            }

            foreach ($roleConfig->defaultTranslations as $locale => $meta) {
                $locale = trim((string) $locale);
                if ($locale === '') {
                    continue;
                }

                $this->upsertLangValueByKey(
                    $locale,
                    $rolePrefix . $roleName . '.name',
                    $meta['display_name'] !== '' ? $meta['display_name'] : $roleName
                );
                $this->upsertLangValueByKey(
                    $locale,
                    $rolePrefix . $roleName . '.description',
                    $meta['description']
                );
            }
        }

        foreach ($this->usimConfig->permissions as $permissionName => $permissionConfig) {
            $permissionName = trim((string) $permissionName);
            if ($permissionName === '') {
                continue;
            }

            foreach ($permissionConfig->defaultTranslations as $locale => $meta) {
                $locale = trim((string) $locale);
                if ($locale === '') {
                    continue;
                }

                $this->upsertLangValueByKey(
                    $locale,
                    $permissionPrefix . $permissionName . '.name',
                    $meta['display_name'] !== '' ? $meta['display_name'] : $permissionName
                );
                $this->upsertLangValueByKey(
                    $locale,
                    $permissionPrefix . $permissionName . '.description',
                    $meta['description']
                );
            }
        }
    }

    private function normalizeTranslationPrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return '';
        }

        return str_ends_with($prefix, '.') ? $prefix : $prefix . '.';
    }

    private function upsertLangValueByKey(string $locale, string $translationKey, string $value): void
    {
        $locale = trim($locale);
        $translationKey = trim($translationKey);

        if ($locale === '' || $translationKey === '') {
            return;
        }

        $segments = array_values(array_filter(explode('.', $translationKey), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) < 2) {
            return;
        }

        $file = array_shift($segments);
        $langDir = $this->baseLangPath !== null
            ? rtrim($this->baseLangPath, '/\\') . DIRECTORY_SEPARATOR . $locale
            : lang_path($locale);
        $langFile = $langDir . DIRECTORY_SEPARATOR . $file . '.php';

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

        if (!is_array($loaded)) {
            return [];
        }

        /** @var array<string, mixed> $loaded */
        return $loaded;
    }
}
