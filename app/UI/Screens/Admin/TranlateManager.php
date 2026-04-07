<?php

namespace App\UI\Screens\Admin;

use App\UI\Components\Modals\EditTranslationDialog;
use App\UI\Components\DataTable\TranslationKeysTableModel;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\InputBuilder;
use Idei\Usim\Services\Components\SelectBuilder;
use Idei\Usim\Services\Components\TableBuilder;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Enums\DialogType;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\Modals\ConfirmDialogService;
use Idei\Usim\Services\Support\TranslationService;
use Idei\Usim\Services\UIBuilder;

class TranlateManager extends AbstractUIService
{
    protected TableBuilder $translations_table;
    protected InputBuilder $search_translations;
    protected SelectBuilder $language_filter;
    protected SelectBuilder $group_filter;

    public static function authorize(): bool
    {
        return self::requireRole('admin');
    }

    public static function getMenuLabel(): string
    {
        return t('translations');
    }

    public static function getMenuIcon(): ?string
    {
        return '🌐';
    }

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->maxWidth('1360px')
            ->centerHorizontal()
            ->padding('10px')
            ->plain();

        $toolbar = UIBuilder::container('translations_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->shadow(0)
            ->width('100%')
            ->gap('12px');

        $search = UIBuilder::input('search_translations')
            ->label(t('Search'))
            ->placeholder(t('Search keys or groups...'))
            ->width('420px')
            ->autocomplete('off')
            ->onInput('search_translations', [])
            ->debounce(500);

        $languageFilter = UIBuilder::select('language_filter')
            ->label(t('Language'))
            ->placeholder(t('Choose a language...'))
            ->options($this->getLanguageOptions())
            ->value('all')
            ->onChange('language_filter_change')
            ->style('primary')
            ->width('200px');

        $groupFilter = UIBuilder::select('group_filter')
            ->label(t('Group'))
            ->placeholder(t('Choose a group...'))
            ->options($this->getGroupOptions())
            ->value('all')
            ->searchable(true, t('Search groups...'))
            ->onChange('group_filter_change')
            ->style('primary')
            ->width('200px');

        $toolbar->add($search)->add($languageFilter)->add($groupFilter);
        $container->add($toolbar);

        $table = UIBuilder::table('translations_table')
            ->pagination(9)
            ->sortedBy('key')
            ->dataModel(TranslationKeysTableModel::class)
            ->width('100%')
            ->rowMinHeight(44);

        $container->add($table);
    }

    protected function postLoadUI(): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $search = $this->translations_table->getSearchTerm();
        $this->search_translations->value($search ?? '');
        $this->language_filter->value($model->getLanguageFilter());
        $this->group_filter->value($model->getGroupFilter());
    }

    public function onSearchTranslations(array $params): void
    {
        $search = (string) ($params['value'] ?? '');
        $this->translations_table->setSearchTerm($search);
    }

    public function onLanguageFilterChange(array $params): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $model->setLanguageFilter((string) ($params['value'] ?? 'all'));
        $this->translations_table->refreshColumns();
        $this->translations_table->page(1);
    }

    public function onGroupFilterChange(array $params): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $model->setGroupFilter((string) ($params['value'] ?? 'all'));
        $this->translations_table->page(1);
    }

    public function onTranslationsTableColumnClicked(array $params): void
    {
        $column = $params['sort_by'] ?? null;
        if (!$column) {
            return;
        }

        $this->translations_table->sortedBy($column);
        $this->translations_table->page(1);
    }

    public function onChangePage(array $params): void
    {
        $page = (int) ($params['page'] ?? 1);
        $this->translations_table->page($page);
    }

    public function onEditTranslation(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        if ($key === '') {
            $this->toast(t('Translation key is required'), 'error');
            return;
        }

        $languages = $this->resolveEditableLanguages();
        $fallbackCode = $languages['fallback'];
        $selectedCode = $languages['selected'];

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $fallbackEntry = $translationService->getDirectEntry($key, $fallbackCode);
        $selectedEntry = $selectedCode ? $translationService->getDirectEntry($key, $selectedCode) : null;

        EditTranslationDialog::open(
            key: $key,
            group: (string) ($params['group'] ?? ''),
            fallbackLanguageCode: $fallbackCode,
            selectedLanguageCode: $selectedCode,
            fallbackText: (string) ($fallbackEntry['text'] ?? ''),
            selectedText: (string) ($selectedEntry['text'] ?? ''),
            fallbackNeedsReview: (bool) ($fallbackEntry['needs_review'] ?? false),
            selectedNeedsReview: (bool) ($selectedEntry['needs_review'] ?? false),
            callerServiceId: $this->getServiceComponentId()
        );
    }

    public function onDeleteTranslation(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        if ($key === '') {
            $this->toast(t('Translation key is required'), 'error');
            return;
        }

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t('Delete Translation'),
            message: t("Are you sure you want to delete translation ':key'?", ['key' => $key]),
            confirmAction: 'confirm_delete_translation',
            confirmParams: ['key' => $key],
            callerServiceId: $this->getServiceComponentId()
        );
    }

    public function onConfirmDeleteTranslation(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        if ($key === '') {
            $this->toast(t('Translation key is required for deletion'), 'error');
            return;
        }

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $deleted = $translationService->deleteKey($key);

        if (!$deleted) {
            $this->toast(t('Translation key not found'), 'error');
            return;
        }

        $this->translations_table->refresh();
        $this->toast(t('Translation deleted successfully'), 'success');
        $this->closeModal();
    }

    public function onSubmitUpdateTranslation(array $params): void
    {
        $key = (string) ($params['translation_key'] ?? '');
        $fallbackLanguageCode = (string) ($params['fallback_language_code'] ?? '');
        $selectedLanguageCode = (string) ($params['selected_language_code'] ?? '');

        if ($key === '' || $fallbackLanguageCode === '') {
            $this->toast(t('Translation update payload is incomplete'), 'error');
            return;
        }

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $fallbackReviewed = $this->normalizeCheckboxValue($params['fallback_mark_reviewed'] ?? false);
        $fallbackNeedsReview = !$fallbackReviewed;

        $translationService->upsertValue(
            $key,
            $fallbackLanguageCode,
            (string) ($params['fallback_text'] ?? ''),
            needsReview: $fallbackNeedsReview
        );

        if ($selectedLanguageCode !== '' && $selectedLanguageCode !== $fallbackLanguageCode) {
            $selectedReviewed = $this->normalizeCheckboxValue($params['selected_mark_reviewed'] ?? false);
            $selectedNeedsReview = !$selectedReviewed;

            $translationService->upsertValue(
                $key,
                $selectedLanguageCode,
                (string) ($params['selected_text'] ?? ''),
                needsReview: $selectedNeedsReview
            );
        }

        $this->translations_table->refresh();
        $this->toast(t('Translation updated successfully'), 'success');
        $this->closeModal();
    }

    protected function normalizeCheckboxValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    protected function getLanguageOptions(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listLanguagesDataset();

        $options = [
            ['value' => 'all', 'label' => t('None')],
        ];

        // Resolve fallback code to exclude it from options
        $fallbackCode = 'en';
        foreach (($dataset['items'] ?? []) as $language) {
            if ((bool) ($language['is_fallback'] ?? false)) {
                $fallbackCode = (string) ($language['code'] ?? 'en');
            }
        }

        foreach (($dataset['items'] ?? []) as $language) {
            $code = (string) ($language['code'] ?? '');
            if ($code === '' || $code === $fallbackCode) {
                continue;
            }

            $label = (string) ($language['native_name'] ?? $language['name'] ?? strtoupper($code));
            $options[] = [
                'value' => $code,
                'label' => strtoupper($code) . ' - ' . $label,
            ];
        }

        return $options;
    }

    protected function getGroupOptions(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listKeyGroupsDataset();

        $options = [
            ['value' => 'all', 'label' => t('All groups')],
        ];

        foreach (($dataset['items'] ?? []) as $group) {
            $value = (string) ($group['group'] ?? '');
            if ($value === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $value,
            ];
        }

        return $options;
    }

    protected function resolveEditableLanguages(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listLanguagesDataset();

        $fallbackCode = 'en';
        $firstActiveNonFallback = null;

        foreach (($dataset['items'] ?? []) as $language) {
            $code = (string) ($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            if ((bool) ($language['is_fallback'] ?? false)) {
                $fallbackCode = $code;
            }

            if ((bool) ($language['is_active'] ?? false) && !(bool) ($language['is_fallback'] ?? false) && $firstActiveNonFallback === null) {
                $firstActiveNonFallback = $code;
            }
        }

        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $filterCode = $model->getLanguageFilter();

        // Selected is null when no specific language is chosen, or when the filter matches the fallback itself
        $selectedCode = ($filterCode === 'all' || $filterCode === $fallbackCode) ? null : $filterCode;

        return [
            'fallback' => $fallbackCode,
            'selected' => $selectedCode,
        ];
    }
}
