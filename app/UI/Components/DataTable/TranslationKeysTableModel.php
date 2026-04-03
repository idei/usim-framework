<?php

namespace App\UI\Components\DataTable;

use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Services\Components\TableBuilder;
use Idei\Usim\Services\DataTable\AbstractDataTableModel;
use Idei\Usim\Services\Support\TranslationService;
use Idei\Usim\Services\Support\UIStateManager;

class TranslationKeysTableModel extends AbstractDataTableModel
{
    protected TranslationService $translationService;
    protected const SEARCH_KEY = 'translation_table_search';
    protected const LANGUAGE_FILTER_KEY = 'translation_table_language_filter';
    protected const GROUP_FILTER_KEY = 'translation_table_group_filter';

    /** @var array<string, string> */
    protected array $languages = [];

    private const EDIT_ICON_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
SVG;

    private const DELETE_ICON_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14H7L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
SVG;

    public function __construct(TableBuilder $tableBuilder)
    {
        parent::__construct($tableBuilder);

        $this->translationService = app(TranslationService::class);

        $dataset = $this->translationService->listLanguagesDataset();
        foreach (($dataset['items'] ?? []) as $language) {
            $code = (string) ($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $name = (string) ($language['native_name'] ?? $language['name'] ?? strtoupper($code));
            $this->languages[$code] = $name;
        }
    }

    public function getColumns(): array
    {
        $columns = [
            'key' => ['label' => 'Key', 'width' => [180, 220], 'sort_by' => 'key'],
            'needs_review' => ['label' => 'Needs Review', 'width' => [130, 150], 'sort_by' => 'needs_review'],
            'group' => ['label' => 'Group', 'width' => [180, 220]],
        ];

        foreach ($this->languages as $code => $name) {
            $columns['lang_' . $code] = [
                'label' => strtoupper($code) . ' (' . $name . ')',
                'width' => [170, 210],
            ];
        }

        $columns['edit'] = ['label' => '', 'width' => [52, 56]];
        $columns['delete'] = ['label' => '', 'width' => [52, 56]];

        return $columns;
    }

    protected function getAllData(): array
    {
        return [];
    }

    protected function countTotal(): int
    {
        return $this->buildBaseQuery()->count();
    }

    public function setSearchTerm(?string $searchTerm): void
    {
        UIStateManager::storeKeyValue(self::SEARCH_KEY, $searchTerm);
    }

    public function getSearchTerm(): ?string
    {
        return UIStateManager::getKeyValue(self::SEARCH_KEY);
    }

    public function clearSearch(): void
    {
        UIStateManager::clearKeyValue(self::SEARCH_KEY);
    }

    public function setLanguageFilter(?string $languageCode): void
    {
        UIStateManager::storeKeyValue(self::LANGUAGE_FILTER_KEY, $this->normalizeFilterValue($languageCode));
    }

    public function getLanguageFilter(): string
    {
        return $this->normalizeFilterValue(UIStateManager::getKeyValue(self::LANGUAGE_FILTER_KEY));
    }

    public function setGroupFilter(?string $group): void
    {
        UIStateManager::storeKeyValue(self::GROUP_FILTER_KEY, $this->normalizeFilterValue($group));
    }

    public function getGroupFilter(): string
    {
        return $this->normalizeFilterValue(UIStateManager::getKeyValue(self::GROUP_FILTER_KEY));
    }

    public function getPageData(): array
    {
        $pagination = $this->tableBuilder->getPaginationData();
        $sortBy = $this->tableBuilder->getSortColumn();
        $sortDirection = $this->tableBuilder->getSortDirection();

        $allowedSorts = ['key', 'needs_review'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'key';
        $direction = strtolower((string) $sortDirection) === 'desc' ? 'desc' : 'asc';

        $query = $this->buildBaseQuery()
            ->with(['values.language'])
            ->orderBy($sortColumn, $direction);

        return $query
            ->forPage((int) $pagination['current_page'], (int) $pagination['per_page'])
            ->get()
            ->all();
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $rows = $this->getPageData();
        $formatted = [];

        foreach ($rows as $row) {
            $rowData = [
                'key' => $row->key,
                'needs_review' => $row->needs_review ? 'Yes' : 'No',
                'group' => $row->group ?? '-',
            ];

            $valuesByCode = [];
            foreach ($row->values as $value) {
                $code = $value->language?->code;
                if (!$code) {
                    continue;
                }

                $valuesByCode[$code] = $value->text_value;
            }

            foreach (array_keys($this->languages) as $code) {
                $text = $valuesByCode[$code] ?? null;
                $rowData['lang_' . $code] = $text !== null && $text !== '' ? $text : '—';
            }

            $rowData['edit'] = [
                'button' => [
                    'label' => 'Edit',
                    'icon' => $this->svgDataUri(self::EDIT_ICON_SVG),
                    'icon_only' => true,
                    'tooltip' => 'Edit translation',
                    'icon_size' => 16,
                    'action' => 'edit_translation',
                    'style' => 'secondary',
                    'parameters' => [
                        'key' => $row->key,
                        'group' => $row->group,
                    ],
                ],
            ];

            $rowData['delete'] = [
                'button' => [
                    'label' => 'Delete',
                    'icon' => $this->svgDataUri(self::DELETE_ICON_SVG),
                    'icon_only' => true,
                    'tooltip' => 'Delete translation',
                    'icon_size' => 16,
                    'action' => 'delete_translation',
                    'style' => 'danger',
                    'parameters' => [
                        'key' => $row->key,
                        'group' => $row->group,
                    ],
                ],
            ];

            $formatted[] = $rowData;
        }

        return $formatted;
    }

    protected function buildBaseQuery()
    {
        $searchTerm = trim((string) ($this->getSearchTerm() ?? ''));
        $languageFilter = $this->getLanguageFilter();
        $groupFilter = $this->getGroupFilter();

        return UsimTextKey::query()
            ->where('is_active', true)
            ->when($groupFilter !== 'all', function ($query) use ($groupFilter): void {
                $query->where('group', $groupFilter);
            })
            ->when($languageFilter !== 'all', function ($query) use ($languageFilter): void {
                $query->whereHas('values.language', function ($languageQuery) use ($languageFilter): void {
                    $languageQuery->where('code', $languageFilter);
                });
            })
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $like = '%' . $searchTerm . '%';
                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery->where('key', 'like', $like)
                        ->orWhere('group', 'like', $like);
                });
            });
    }

    protected function normalizeFilterValue(?string $value): string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? 'all' : $normalized;
    }

    protected function svgDataUri(string $svg): string
    {
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
