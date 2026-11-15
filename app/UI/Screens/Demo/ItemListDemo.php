<?php

namespace App\UI\Screens\Demo;

use App\Services\Movie\SessionMovieCatalogService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Select;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;

/**
 * ItemList Demo Screen
 *
 * Demonstrates the vertical ItemList component wired to a capability-based service
 * (SessionMovieCatalogService implementing Listable, Creatable, Updatable, Deletable, BulkDeletable):
 * - Automatic CRUD & search wiring from service contracts via `service` in .layout.yaml
 * - Single, Multiple, and None selection modes (preserving selected movies across searches)
 * - Optional Screen override for ConfirmDialog on Delete / Bulk Delete
 * - Form context value collection when clicking a submit button
 */
class ItemListDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $item_list_demo_title;

    protected NewLabel $item_list_demo_intro;

    protected Select $sel_selection_mode;

    protected Checkbox $chk_confirm_delete;

    protected Button $btn_reset_catalog;

    protected ItemList $movies_list;

    protected NewLabel $lbl_selection_summary;

    protected Button $btn_confirm_marathon;

    /**
     * @var list<int|string>
     */
    protected array $store_pending_delete_ids = [];

    protected string $store_selection_mode = 'multiple';

    protected bool $store_confirm_delete = false;

    protected function startingUI(): Container
    {
        $this->sel_selection_mode
            ->options([
                ['value' => SelectionMode::MULTIPLE->value, 'label' => $this->t('.selection_mode.options.multiple')],
                ['value' => SelectionMode::SINGLE->value, 'label' => $this->t('.selection_mode.options.single')],
                ['value' => SelectionMode::NONE->value, 'label' => $this->t('.selection_mode.options.none')],
            ])
            ->value($this->store_selection_mode);

        $this->chk_confirm_delete->checked($this->store_confirm_delete);

        $items = $this->movies_list->getItems();
        $firstRawId = $items[0]['id'] ?? null;
        /** @var list<int|string> $initialSelected */
        $initialSelected = (is_int($firstRawId) || is_string($firstRawId)) ? [$firstRawId] : [];

        $this->movies_list
            ->selectionMode(SelectionMode::from($this->store_selection_mode))
            ->select($initialSelected);

        if ($this->store_confirm_delete) {
            $this->movies_list
                ->onDelete('movie_deleted')
                ->onBulkDelete('movies_bulk_deleted');
        }

        $this->lbl_selection_summary->text($this->formatSelectionSummary($initialSelected));

        return $this->layoutContainer();
    }

    /**
     * Handle selection mode change (single / multiple / none).
     *
     * @param  array<string, mixed>  $params
     */
    public function onSelectionModeChanged(array $params): void
    {
        $rawMode = $params['value'] ?? SelectionMode::MULTIPLE->value;
        $modeStr = is_string($rawMode) && SelectionMode::isValid($rawMode)
            ? $rawMode
            : SelectionMode::MULTIPLE->value;

        $this->store_selection_mode = $modeStr;
        $mode = SelectionMode::from($modeStr);

        $this->movies_list->selectionMode($mode);
        $this->sel_selection_mode->value($modeStr);

        $selectedIds = $this->movies_list->getSelectedIds();
        $this->lbl_selection_summary->text($this->formatSelectionSummary($selectedIds));
    }

    /**
     * Toggle between direct service deletion and ConfirmDialog interception.
     *
     * @param  array<string, mixed>  $params
     */
    public function onToggleConfirmDelete(array $params): void
    {
        $checked = (bool) ($params['checked'] ?? false);
        $this->store_confirm_delete = $checked;
        $this->chk_confirm_delete->checked($checked);

        if ($checked) {
            $this->movies_list
                ->onDelete('movie_deleted')
                ->onBulkDelete('movies_bulk_deleted');
        } else {
            $this->movies_list->useServiceDeleteHandlers();
        }
    }

    /**
     * Handle selection change from ItemList.
     *
     * @param  array<string, mixed>  $params
     */
    public function onMovieSelected(array $params): void
    {
        $rawSelected = $params['selected'] ?? [];
        $selectedIds = is_array($rawSelected)
            ? array_values(array_filter($rawSelected, static fn (mixed $v): bool => is_int($v) || is_string($v)))
            : [];

        $this->movies_list->select($selectedIds);
        $this->lbl_selection_summary->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()));
    }

    /**
     * Keep selection summary synchronized after service-driven CRUD mutations.
     */
    public function onCatalogMutated(): void
    {
        $this->lbl_selection_summary->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()));
    }

    /**
     * Optional ConfirmDialog interception for single movie deletion.
     *
     * @param  array<string, mixed>  $params
     */
    public function onMovieDeleted(array $params): void
    {
        $rawId = $params['id'] ?? null;
        if (! is_int($rawId) && ! is_string($rawId)) {
            return;
        }

        if ($this->store_confirm_delete) {
            $this->store_pending_delete_ids = [$rawId];
            $movie = $this->movies_list->findItem($rawId);
            $movieLabel = is_array($movie) && isset($movie['label']) && is_string($movie['label'])
                ? $movie['label']
                : (string) $rawId;

            ConfirmDialog::open(
                caller: $this,
                type: DialogType::CONFIRM,
                title: 'Confirmar eliminación',
                message: "¿Seguro que deseas eliminar \"{$movieLabel}\" del catálogo?",
                confirmAction: 'confirm_delete_movies',
                cancelAction: 'cancel_delete_movies'
            );

            return;
        }

        $this->movies_list->performServiceDelete($rawId, $this);
    }

    /**
     * Optional ConfirmDialog interception for bulk deletion of selected movies.
     *
     * @param  array<string, mixed>  $params
     */
    public function onMoviesBulkDeleted(array $params): void
    {
        $rawIds = $params['ids'] ?? $params['selected'] ?? [];
        if (! is_array($rawIds) || $rawIds === []) {
            return;
        }

        /** @var list<int|string> $ids */
        $ids = array_values(array_filter($rawIds, static fn (mixed $v): bool => is_int($v) || is_string($v)));
        if ($ids === []) {
            return;
        }

        if ($this->store_confirm_delete) {
            $this->store_pending_delete_ids = $ids;
            $count = count($ids);

            ConfirmDialog::open(
                caller: $this,
                type: DialogType::CONFIRM,
                title: 'Confirmar eliminación masiva',
                message: "¿Seguro que deseas eliminar las {$count} películas seleccionadas?",
                confirmAction: 'confirm_delete_movies',
                cancelAction: 'cancel_delete_movies'
            );

            return;
        }

        $this->movies_list->performServiceBulkDelete($ids, $this);
    }

    /**
     * Callback when ConfirmDialog confirms deletion.
     */
    public function onConfirmDeleteMovies(): void
    {
        $ids = $this->store_pending_delete_ids;
        $this->store_pending_delete_ids = [];
        $this->closeModal();

        if ($ids === []) {
            return;
        }

        if (count($ids) === 1) {
            $this->movies_list->performServiceDelete($ids[0], $this);
        } else {
            $this->movies_list->performServiceBulkDelete($ids, $this);
        }
    }

    /**
     * Callback when ConfirmDialog cancels deletion.
     */
    public function onCancelDeleteMovies(): void
    {
        $this->store_pending_delete_ids = [];
        $this->closeModal();
    }

    /**
     * Handle form submission button ("Confirmar Maratón").
     * Demonstrates automatic collection of ItemList selected values via collectContextValues().
     *
     * @param  array<string, mixed>  $params
     */
    public function onConfirmMarathon(array $params): void
    {
        $selectedIds = $this->movies_list->getSelectedIds();

        if ($selectedIds === []) {
            $this->movies_list->error('Selecciona al menos una película para armar tu maratón');
            $this->toast('Selecciona al menos una película', 'warning');

            return;
        }

        $this->movies_list->error(null);
        $summary = $this->formatSelectionSummary($selectedIds);
        $this->lbl_selection_summary
            ->text($summary)
            ->style('success');

        $count = count($selectedIds);
        $this->toast("¡Maratón confirmado con {$count} película(s)!", 'success');
    }

    /**
     * Restore initial catalog in the session-backed service.
     */
    public function onResetCatalog(): void
    {
        $service = $this->movies_list->getService();
        if ($service instanceof SessionMovieCatalogService) {
            $service->reset();
        }

        $this->movies_list
            ->error(null)
            ->refreshFromService();

        $items = $this->movies_list->getItems();
        $firstRawId = $items[0]['id'] ?? null;
        /** @var list<int|string> $firstId */
        $firstId = (is_int($firstRawId) || is_string($firstRawId)) ? [$firstRawId] : [];
        $this->movies_list->select($firstId);

        $this->lbl_selection_summary
            ->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()))
            ->style('default');

        $this->toast('Catálogo de películas restaurado', 'info');
    }

    /**
     * @param  list<int|string>  $selectedIds
     */
    private function formatSelectionSummary(array $selectedIds): string
    {
        if ($this->store_selection_mode === SelectionMode::NONE->value) {
            return 'ℹ️ Modo sin selección activo: puedes buscar, agregar, editar o eliminar películas.';
        }

        if ($selectedIds === []) {
            return 'Ninguna película seleccionada.';
        }

        $selectedSet = array_map(static fn (int|string $id): string => (string) $id, $selectedIds);
        /** @var list<string> $labels */
        $labels = [];

        foreach ($this->movies_list->getItems() as $movie) {
            $movieId = $movie['id'] ?? null;
            $movieLabel = $movie['label'] ?? null;
            if ((is_int($movieId) || is_string($movieId))
                && is_string($movieLabel)
                && in_array((string) $movieId, $selectedSet, true)
            ) {
                $labels[] = $movieLabel;
            }
        }

        $count = count($labels);
        $joined = implode(', ', array_slice($labels, 0, 4));
        if ($count > 4) {
            $extra = $count - 4;
            $joined .= " (+{$extra} más)";
        }

        return "✅ Seleccionadas ({$count}): {$joined}";
    }
}
