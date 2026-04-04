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

    /** @var array<int, string> */
    protected array $languageCodes = [];

    protected string $fallbackCode = 'en';

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
            $this->languageCodes[] = $code;

            if ((bool) ($language['is_fallback'] ?? false)) {
                $this->fallbackCode = $code;
            }
        }
    }

    public function getColumns(): array
    {
        $columns = [
            // Fixed width: keep key column stable regardless of content length.
            'key' => ['label' => 'Key', 'width' => [320, 320], 'sort_by' => 'key'],
            'completion' => ['label' => 'Completion', 'width' => [120, 140]],
        ];

        // Fallback language column — always visible
        $fallbackName = $this->languages[$this->fallbackCode] ?? strtoupper($this->fallbackCode);
        $columns['lang_fallback'] = [
            'label' => strtoupper($this->fallbackCode) . ' (' . $fallbackName . ')',
            // Fixed width: keep fallback translation column stable regardless of content length.
            'width' => [320, 320],
        ];

        // Selected language column — always present to keep column count stable;
        // collapsed to width 0 when no non-fallback language is selected.
        $selectedCode = $this->getLanguageFilter();
        $hasSelectedLang = $selectedCode !== 'all'
            && $selectedCode !== $this->fallbackCode
            && isset($this->languages[$selectedCode]);

        if ($hasSelectedLang) {
            $selectedName = $this->languages[$selectedCode];
            $columns['lang_selected'] = [
                'label' => strtoupper($selectedCode) . ' (' . $selectedName . ')',
                'width' => [170, 210],
            ];
        } else {
            $columns['lang_selected'] = [
                'label' => '',
                'width' => [0, 0],
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

        $allowedSorts = ['key'];
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
            ];

            $valuesByCode = [];
            foreach ($row->values as $value) {
                $code = $value->language?->code;
                if (!$code) {
                    continue;
                }

                $valuesByCode[$code] = [
                    'text' => $value->text_value,
                    'needs_review' => (bool) $value->needs_review,
                ];
            }

            $rowData['completion'] = $this->buildCompletionCell($valuesByCode);

            // Fallback column — always present
            $fallbackEntry = $valuesByCode[$this->fallbackCode] ?? null;
            $rowData['lang_fallback'] = $this->buildTranslationCell($fallbackEntry);

            // Selected column — always present in row data to match fixed column count
            $selectedCode = $this->getLanguageFilter();
            $hasSelectedLang = $selectedCode !== 'all'
                && $selectedCode !== $this->fallbackCode
                && isset($this->languages[$selectedCode]);

            if ($hasSelectedLang) {
                $selectedEntry = $valuesByCode[$selectedCode] ?? null;
                $rowData['lang_selected'] = $this->buildTranslationCell($selectedEntry);
            } else {
                $rowData['lang_selected'] = '';
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

    /**
     * @param array{text?: mixed, needs_review?: bool}|null $entry
     * @return string|array<string, string>
     */
    protected function buildTranslationCell(?array $entry): string|array
    {
        $text = trim((string) ($entry['text'] ?? ''));
        if ($text === '') {
            return '—';
        }

        if (!(bool) ($entry['needs_review'] ?? false)) {
            return $text;
        }

        return [
            'text' => $text,
            'background_color' => 'var(--usim-label-warning-bg, rgba(243, 156, 18, 0.18))',
            'text_color' => 'var(--usim-label-warning-text, #8a5b12)',
            'border_color' => 'var(--usim-label-warning-border, rgba(243, 156, 18, 0.35))',
        ];
    }

    /**
     * @param array<string, array{text?: mixed, needs_review?: bool}> $valuesByCode
     * @return array<string, string>
     */
    protected function buildCompletionCell(array $valuesByCode): array
    {
        $totalLanguages = count($this->languageCodes);
        $completedTranslations = 0;

        foreach ($this->languageCodes as $code) {
            $entry = $valuesByCode[$code] ?? null;
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                $completedTranslations++;
            }
        }

        $percentage = $totalLanguages > 0
            ? (int) round(($completedTranslations / $totalLanguages) * 100)
            : 0;

        return [
            'text' => $percentage . '%',
            'background_color' => $this->resolveCompletionBackgroundColor($percentage),
            'text_color' => $this->resolveCompletionTextColor($percentage),
            'border_color' => $this->resolveCompletionBorderColor($percentage),
        ];
    }

    protected function resolveCompletionBackgroundColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-bg, rgba(46, 204, 113, 0.16))';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-bg, rgba(243, 156, 18, 0.18))';
        }

        return 'rgba(230, 126, 34, 0.16)';
    }

    protected function resolveCompletionTextColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-text, #1d6f44)';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-text, #8a5b12)';
        }

        return '#9a5614';
    }

    protected function resolveCompletionBorderColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-border, rgba(46, 204, 113, 0.35))';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-border, rgba(243, 156, 18, 0.35))';
        }

        return 'rgba(230, 126, 34, 0.32)';
    }

    protected function buildBaseQuery()
    {
        $searchTerm = trim((string) ($this->getSearchTerm() ?? ''));
        $groupFilter = $this->getGroupFilter();

        return UsimTextKey::query()
            ->where('is_active', true)
            ->when($groupFilter !== 'all', function ($query) use ($groupFilter): void {
                $query->where('group', $groupFilter);
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
