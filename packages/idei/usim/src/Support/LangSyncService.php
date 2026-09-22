<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimLanguage;
use Illuminate\Support\Facades\DB;

class LangSyncService
{
    protected UsimConfig $usimConfig;

    public function __construct(?UsimConfig $usimConfig = null)
    {
        $this->usimConfig = $usimConfig ?? app(UsimConfig::class);
    }

    /**
     * Synchronizes the languages configured in the USIM configuration with the database.
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
            $fallbackCode = is_string($appFallback) && trim($appFallback) !== '' ? trim($appFallback) : 'en';
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
}
