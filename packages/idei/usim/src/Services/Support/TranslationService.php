<?php

namespace Idei\Usim\Services\Support;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Models\UsimTextValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class TranslationService
{
    public function upsertLanguage(
        string $code,
        string $name,
        ?string $nativeName = null,
        bool $isActive = true,
        bool $isFallback = false
    ): UsimLanguage {
        $language = UsimLanguage::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'native_name' => $nativeName,
                'is_active' => $isActive,
                'is_fallback' => $isFallback,
            ]
        );

        if ($isFallback) {
            UsimLanguage::query()
                ->where('id', '!=', $language->id)
                ->update(['is_fallback' => false]);
        }

        return $language;
    }

    public function createOrUpdateKey(string $key, array $attributes = []): UsimTextKey
    {
        $textKey = UsimTextKey::query()->firstOrNew(['key' => $key]);

        if (!$textKey->exists) {
            $textKey->is_active = (bool) ($attributes['is_active'] ?? true);
            $textKey->needs_review = (bool) ($attributes['needs_review'] ?? false);
        }

        if (\array_key_exists('group', $attributes)) {
            $textKey->group = $attributes['group'];
        }

        if (\array_key_exists('needs_review', $attributes)) {
            $textKey->needs_review = (bool) $attributes['needs_review'];
        }

        if (\array_key_exists('is_active', $attributes)) {
            $textKey->is_active = (bool) $attributes['is_active'];
        }

        $textKey->save();

        return $textKey;
    }

    public function upsertValue(
        string $key,
        string $languageCode,
        ?string $text,
        ?string $mediaUrl = null,
        ?array $mediaMeta = null
    ): UsimTextValue {
        $textKey = $this->createOrUpdateKey($key);
        $language = UsimLanguage::query()->byCode($languageCode)->firstOrFail();

        return UsimTextValue::query()->updateOrCreate(
            [
                'text_key_id' => $textKey->id,
                'language_id' => $language->id,
            ],
            [
                'text_value' => $text,
                'media_url' => $mediaUrl,
                'media_meta' => $mediaMeta,
            ]
        );
    }

    public function deleteKey(string $key): bool
    {
        return (bool) UsimTextKey::query()->byKey($key)->delete();
    }

    public function deleteValue(string $key, string $languageCode): bool
    {
        $textKey = UsimTextKey::query()->byKey($key)->first();

        if (!$textKey) {
            return false;
        }

        $language = UsimLanguage::query()->byCode($languageCode)->first();

        if (!$language) {
            return false;
        }

        return (bool) UsimTextValue::query()
            ->where('text_key_id', $textKey->id)
            ->where('language_id', $language->id)
            ->delete();
    }

    public function listKeys(?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        return UsimTextKey::query()
            ->when($search, function ($query) use ($search) {
                $query->where('key', 'like', '%' . $search . '%')
                    ->orWhere('group', 'like', '%' . $search . '%');
            })
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function listLanguagesDataset(): array
    {
        $items = UsimLanguage::query()
            ->orderByDesc('is_fallback')
            ->orderBy('name')
            ->get()
            ->map(static function (UsimLanguage $language): array {
                return [
                    'id' => $language->id,
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'is_active' => (bool) $language->is_active,
                    'is_fallback' => (bool) $language->is_fallback,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
            'type' => 'list',
        ];
    }

    public function listKeyGroupsDataset(): array
    {
        $items = UsimTextKey::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->map(static fn (string $group): array => ['group' => $group])
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
            'type' => 'list',
        ];
    }

    public function listKeysByLanguageDataset(
        string $languageCode,
        ?string $group = null,
        ?string $filter = null,
        string $sortBy = 'key',
        string $sortDirection = 'asc',
        int $perPage = 50,
        int $page = 1
    ): array {
        $language = UsimLanguage::query()->byCode($languageCode)->firstOrFail();
        $normalizedSortBy = in_array($sortBy, ['key', 'needs_review'], true) ? $sortBy : 'key';
        $normalizedDirection = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        $normalizedPerPage = max(1, min($perPage, 200));
        $normalizedPage = max(1, $page);
        $normalizedGroup = $group !== null && strtolower($group) !== 'all' ? $group : null;

        $paginator = UsimTextKey::query()
            ->where('is_active', true)
            ->when($normalizedGroup !== null, function ($query) use ($normalizedGroup): void {
                $query->where('group', $normalizedGroup);
            })
            ->when($filter !== null && trim($filter) !== '', function ($query) use ($filter): void {
                $like = '%' . trim($filter) . '%';

                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery->where('key', 'like', $like)
                        ->orWhere('group', 'like', $like);
                });
            })
            ->with(['values' => function ($query) use ($language): void {
                $query->where('language_id', $language->id);
            }])
            ->orderBy($normalizedSortBy)
            ->paginate($normalizedPerPage, ['*'], 'page', $normalizedPage);

        $items = $paginator->getCollection()
            ->map(function (UsimTextKey $textKey): array {
                $value = $textKey->values->first();
                $hasRepresentation = $value !== null;

                return [
                    'id' => $textKey->id,
                    'key' => $textKey->key,
                    'group' => $textKey->group,
                    'needs_review' => (bool) $textKey->needs_review,
                    'is_active' => (bool) $textKey->is_active,
                    'has_representation' => $hasRepresentation,
                    'missing_representation' => !$hasRepresentation,
                    'text' => $value?->text_value,
                    'media_url' => $value?->media_url,
                    'media_meta' => $value?->media_meta,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'has_next' => $paginator->hasMorePages(),
                'has_previous' => $paginator->currentPage() > 1,
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'previous_page' => $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
                'first_page_url' => $paginator->url(1),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
            'count' => count($items),
            'type' => 'paginated_list',
        ];
    }

    public function getValue(string $key, array $params = [], ?string $languageCode = null): string
    {
        $resolvedKey = $this->resolveOrRegisterKey($key);
        $textValue = $this->resolveTextValue($resolvedKey, $languageCode);

        if ($textValue === null || $textValue === '') {
            return $key;
        }

        return $this->replaceParams($textValue, $params);
    }

    public function getEntry(string $key, ?string $languageCode = null): ?array
    {
        $resolvedKey = $this->resolveOrRegisterKey($key);
        $entry = $this->resolveValueEntry($resolvedKey, $languageCode);

        if (!$entry) {
            return null;
        }

        return [
            'text' => $entry->text_value,
            'media_url' => $entry->media_url,
            'media_meta' => $entry->media_meta,
            'language_code' => $entry->language?->code,
            'key' => $entry->textKey?->key,
        ];
    }

    protected function resolveTextValue(string $key, ?string $languageCode): ?string
    {
        $entry = $this->resolveValueEntry($key, $languageCode);

        return $entry?->text_value;
    }

    protected function resolveOrRegisterKey(string $input): string
    {
        if ($this->isSlug($input)) {
            if (!UsimTextKey::query()->byKey($input)->exists()) {
                $callerContext = $this->resolveCallerContext();
                $this->storeFallbackTranslation($input, $this->slugToFallbackText($input), true, $callerContext['group']);
            }

            return $input;
        }

        $callerContext = $this->resolveCallerContext();
        $generatedKey = $this->generateAutoKeyFromText($input, $callerContext['group']);
        $this->storeFallbackTranslation($generatedKey, $input, true, $callerContext['group']);

        return $generatedKey;
    }

    protected function isSlug(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // ASCII only, lowercase separators allowed only in-between segments.
        return preg_match('/^(?:[a-z0-9]{3,})(?:[._][a-z0-9]{3,})*$/', $value) === 1;
    }

    protected function storeFallbackTranslation(
        string $key,
        string $fallbackText,
        ?bool $needsReview = null,
        ?string $group = null
    ): void
    {
        if ($needsReview !== null || $group !== null) {
            $attributes = [];

            if ($needsReview !== null) {
                $attributes['needs_review'] = $needsReview;
            }

            if ($group !== null) {
                $attributes['group'] = $group;
            }

            $this->createOrUpdateKey($key, $attributes);
        }

        $fallbackLocale = $this->resolveFallbackLocale();
        $this->ensureLanguageExists($fallbackLocale);
        $this->upsertValue($key, $fallbackLocale, $fallbackText);
    }

    protected function ensureLanguageExists(string $code): void
    {
        if (UsimLanguage::query()->byCode($code)->exists()) {
            return;
        }

        $hasFallback = UsimLanguage::query()->where('is_fallback', true)->exists();
        $name = strtoupper($code);
        $this->upsertLanguage($code, $name, $name, true, !$hasFallback);
    }

    protected function slugToFallbackText(string $slug): string
    {
        $sentence = preg_replace('/[._]+/', ' ', strtolower($slug)) ?? $slug;

        return ucfirst(trim($sentence));
    }

    protected function generateAutoKeyFromText(string $text, string $group): string
    {
        $base = $this->buildTextSlugBase($text);

        $existing = UsimTextKey::query()->byKey($base)->first();

        if (!$existing) {
            return $base;
        }

        if ($existing->group === $group) {
            return $base;
        }

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = $base . '_' . random_int(1000, 9999);

            if (!UsimTextKey::query()->byKey($candidate)->exists()) {
                return $candidate;
            }
        }

        return $base . '_' . (string) time();
    }

    protected function buildTextSlugBase(string $text): string
    {
        $slug = Str::slug($text, '_');

        if ($slug === '') {
            $slug = 'text_key';
        }

        return Str::limit($slug, 20, '');
    }

    protected function resolveCallerContext(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $caller = $this->findTranslationCallerFrame($trace);

        $file = $caller['file'] ?? null;
        $stackClass = $caller['class'] ?? null;
        $fileClass = $this->deriveClassFromFilePath($file);
        $class = $this->selectBestCallerClass($stackClass, $fileClass);

        return [
            'group' => $this->normalizeGroupFromClassOrFile($class, $file),
        ];
    }

    protected function selectBestCallerClass(?string $stackClass, ?string $fileClass): ?string
    {
        if (!$stackClass) {
            return $fileClass;
        }

        if (!$fileClass) {
            return $stackClass;
        }

        $stackCount = count($this->splitClassSegments($stackClass));
        $fileCount = count($this->splitClassSegments($fileClass));

        return $fileCount > $stackCount ? $fileClass : $stackClass;
    }

    protected function findTranslationCallerFrame(array $trace): array
    {
        $count = count($trace);

        for ($index = 0; $index < $count; $index++) {
            $function = $trace[$index]['function'] ?? null;

            if ($function === 't' && isset($trace[$index + 1])) {
                return $trace[$index + 1];
            }
        }

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;

            if ($class === self::class || $class === static::class) {
                continue;
            }

            if (isset($frame['file']) || isset($frame['class'])) {
                return $frame;
            }
        }

        return [];
    }

    protected function normalizeGroupFromClassOrFile(?string $class, ?string $file): string
    {
        if ($class) {
            $normalized = preg_replace('/^App\\\\/i', '', $class) ?? $class;
            $segments = $this->splitClassSegments($normalized);

            if ($segments !== []) {
                $className = array_pop($segments);
                $groupParts = array_map(static fn (string $segment): string => strtolower($segment), $segments);
                $groupParts[] = Str::snake($className);

                return implode('.', array_filter($groupParts, static fn (string $part): bool => $part !== ''));
            }
        }

        if ($file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $filename = Str::snake($filename);

            if ($filename !== '') {
                return $filename;
            }
        }

        return 'global';
    }

    protected function splitClassSegments(string $class): array
    {
        if (str_contains($class, '\\')) {
            return array_values(array_filter(explode('\\', $class), static fn (string $segment): bool => $segment !== ''));
        }

        if (str_contains($class, '/')) {
            return array_values(array_filter(explode('/', $class), static fn (string $segment): bool => $segment !== ''));
        }

        if (str_contains($class, '_')) {
            return array_values(array_filter(explode('_', $class), static fn (string $segment): bool => $segment !== ''));
        }

        return [$class];
    }

    protected function deriveClassFromFilePath(?string $file): ?string
    {
        if (!$file) {
            return null;
        }

        $normalizedPath = str_replace('\\\\', '/', $file);
        $appMarker = '/app/';
        $position = stripos($normalizedPath, $appMarker);

        if ($position === false) {
            return null;
        }

        $relative = substr($normalizedPath, $position + strlen($appMarker));

        if ($relative === false || $relative === '') {
            return null;
        }

        $withoutExtension = preg_replace('/\\.php$/i', '', $relative) ?? $relative;
        $segments = array_values(array_filter(explode('/', $withoutExtension), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        return 'App\\' . implode('\\', $segments);
    }

    protected function resolveValueEntry(string $key, ?string $languageCode): ?UsimTextValue
    {
        $targetLocale = $this->resolveLocale($languageCode);
        $fallbackLocale = $this->resolveFallbackLocale();

        $candidates = array_values(array_unique([$targetLocale, $fallbackLocale]));

        foreach ($candidates as $candidateLocale) {
            $entry = UsimTextValue::query()
                ->whereHas('textKey', function ($query) use ($key): void {
                    $query->where('key', $key)->where('is_active', true);
                })
                ->whereHas('language', function ($query) use ($candidateLocale): void {
                    $query->where('code', $candidateLocale)->where('is_active', true);
                })
                ->with(['language', 'textKey'])
                ->first();

            if ($entry && $entry->text_value !== null && $entry->text_value !== '') {
                return $entry;
            }
        }

        return null;
    }

    protected function replaceParams(string $value, array $params): string
    {
        if ($params === []) {
            return $value;
        }

        $replacePairs = [];

        foreach ($params as $name => $replacement) {
            $replacePairs[':' . $name] = (string) $replacement;
        }

        return strtr($value, $replacePairs);
    }

    protected function resolveLocale(?string $languageCode): string
    {
        return $languageCode
            ?? app()->getLocale()
            ?? config('ui-services.i18n.default_locale', 'en');
    }

    protected function resolveFallbackLocale(): string
    {
        return config('ui-services.i18n.fallback_locale', 'en');
    }

    public function safeGetValue(string $key, array $params = [], ?string $languageCode = null): ?string
    {
        try {
            return $this->getValue($key, $params, $languageCode);
        } catch (QueryException) {
            return null;
        }
    }
}
