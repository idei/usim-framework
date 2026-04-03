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
}
