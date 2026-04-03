<?php

namespace Idei\Usim\Services\Support\Translation;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimTextKey;

class TranslationDatasetQuery
{
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
            ->orderBy($normalizedSortBy, $normalizedDirection)
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
}
