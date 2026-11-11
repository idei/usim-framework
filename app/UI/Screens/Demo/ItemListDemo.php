<?php

namespace App\UI\Screens\Demo;

use App\Models\Movie;
use App\Services\Movie\MovieListingService;
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
use Throwable;

/**
 * ItemList Demo Screen
 *
 * Demonstrates the vertical ItemList component using the Movies catalog:
 * - Asynchronous data loading & search via GET /api/ui-data (getMovies) with loading state
 * - Single, Multiple, and None selection modes (preserving selected movies across searches)
 * - Inline CRUD operations (Create, Update, Delete & Bulk Delete)
 * - Optional ConfirmDialog integration on Delete
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
     * Persisted movie catalog in screen state so CRUD mutations work seamlessly
     * with GET /api/ui-data without mutating the shared read-only demo database.
     *
     * @var list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    protected array $store_movies = [];

    /**
     * @var list<int|string>
     */
    protected array $store_pending_delete_ids = [];

    protected string $store_selection_mode = 'multiple';

    protected bool $store_confirm_delete = false;

    // public static function getMenuLabel(): string
    // {
    //     return 'ItemList Demo';
    // }

    // public static function getMenuIcon(): ?string
    // {
    //     return '📋';
    // }

    protected function startingUI(): Container
    {
        $this->ensureCatalogLoaded();

        $this->sel_selection_mode
            ->options([
                ['value' => SelectionMode::MULTIPLE->value, 'label' => $this->t('.selection_mode.options.multiple')],
                ['value' => SelectionMode::SINGLE->value, 'label' => $this->t('.selection_mode.options.single')],
                ['value' => SelectionMode::NONE->value, 'label' => $this->t('.selection_mode.options.none')],
            ])
            ->value($this->store_selection_mode);

        $this->chk_confirm_delete->checked($this->store_confirm_delete);

        $initialSelected = $this->store_movies !== [] ? [$this->store_movies[0]['id']] : [];

        $this->movies_list
            ->items($this->store_movies)
            ->selectionMode(SelectionMode::from($this->store_selection_mode))
            ->select($initialSelected);

        $this->lbl_selection_summary->text($this->formatSelectionSummary($initialSelected));

        return $this->layoutContainer();
    }

    /**
     * Read-only Query Handler invoked via GET /api/ui-data?action=movies.
     *
     * @param  array<string, mixed>  $params
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    public function getMovies(string $search = '', array $params = []): array
    {
        $this->ensureCatalogLoaded();

        $rawSearch = $search !== '' ? $search : ($params['search'] ?? '');
        $term = is_string($rawSearch) ? mb_strtolower(trim($rawSearch)) : '';

        if ($term === '') {
            return $this->store_movies;
        }

        return array_values(array_filter(
            $this->store_movies,
            static function (array $movie) use ($term): bool {
                $haystack = mb_strtolower(
                    $movie['label'].' '.$movie['subtitle'].' '.$movie['badge']
                );

                return str_contains($haystack, $term);
            }
        ));
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
     * Handle toggle for ConfirmDialog on delete.
     *
     * @param  array<string, mixed>  $params
     */
    public function onToggleConfirmDelete(array $params): void
    {
        $checked = (bool) ($params['checked'] ?? false);
        $this->store_confirm_delete = $checked;
        $this->chk_confirm_delete->checked($checked);
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
     * Handle inline creation of a new movie.
     *
     * @param  array<string, mixed>  $params
     */
    public function onMovieCreated(array $params): void
    {
        $rawTitle = $params['value'] ?? $params['label'] ?? '';
        $title = is_string($rawTitle) ? trim($rawTitle) : '';

        if ($title === '') {
            $this->toast('El título de la película es obligatorio', 'warning');

            return;
        }

        $this->ensureCatalogLoaded();

        $nextId = 'custom_'.(count($this->store_movies) + 1).'_'.substr(md5($title.(string) microtime(true)), 0, 5);
        $year = (int) date('Y');

        $newMovie = [
            'id' => $nextId,
            'label' => $title,
            'subtitle' => "Año {$year} • Agregada manualmente al catálogo",
            'icon' => '🍿',
            'badge' => 'Nueva',
            'badge_style' => 'success',
        ];

        array_unshift($this->store_movies, $newMovie);

        $this->movies_list->items($this->store_movies);
        $this->movies_list->reload();

        $this->toast("Película \"{$title}\" agregada al catálogo", 'success');
    }

    /**
     * Handle inline update of a movie title.
     *
     * @param  array<string, mixed>  $params
     */
    public function onMovieUpdated(array $params): void
    {
        $rawId = $params['id'] ?? null;
        $rawValue = $params['value'] ?? $params['label'] ?? '';
        $newTitle = is_string($rawValue) ? trim($rawValue) : '';

        if (($rawId === null || $rawId === '') || $newTitle === '') {
            return;
        }

        if (! is_int($rawId) && ! is_string($rawId)) {
            return;
        }

        $this->ensureCatalogLoaded();
        $targetId = (string) $rawId;

        foreach ($this->store_movies as $index => $movie) {
            if ((string) $movie['id'] === $targetId) {
                $this->store_movies[$index]['label'] = $newTitle;
                break;
            }
        }

        $this->movies_list->updateItem($rawId, ['label' => $newTitle]);
        $this->lbl_selection_summary->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()));
        $this->toast("Película actualizada a \"{$newTitle}\"", 'success');
    }

    /**
     * Handle single movie deletion.
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

        $this->deleteMoviesByIds([$rawId]);
    }

    /**
     * Handle bulk deletion of selected movies.
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

        $this->deleteMoviesByIds($ids);
    }

    /**
     * Callback when ConfirmDialog confirms deletion.
     */
    public function onConfirmDeleteMovies(): void
    {
        $ids = $this->store_pending_delete_ids;
        $this->store_pending_delete_ids = [];
        $this->closeModal();

        if ($ids !== []) {
            $this->deleteMoviesByIds($ids);
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
     * Restore initial catalog from MovieListingService.
     */
    public function onResetCatalog(): void
    {
        $this->store_movies = $this->loadInitialMoviesCatalog();
        $firstId = $this->store_movies !== [] ? [$this->store_movies[0]['id']] : [];

        $this->movies_list
            ->error(null)
            ->items($this->store_movies)
            ->select($firstId)
            ->reload();

        $this->lbl_selection_summary
            ->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()))
            ->style('default');

        $this->toast('Catálogo de películas restaurado', 'info');
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function deleteMoviesByIds(array $ids): void
    {
        $this->ensureCatalogLoaded();
        $removeSet = array_map(static fn (int|string $id): string => (string) $id, $ids);

        $this->store_movies = array_values(array_filter(
            $this->store_movies,
            static fn (array $movie): bool => ! in_array((string) $movie['id'], $removeSet, true)
        ));

        $this->movies_list->removeItems($ids);
        $this->movies_list->reload();

        $this->lbl_selection_summary->text($this->formatSelectionSummary($this->movies_list->getSelectedIds()));

        $count = count($ids);
        $msg = $count === 1 ? 'Película eliminada del catálogo' : "{$count} películas eliminadas del catálogo";
        $this->toast($msg, 'success');
    }

    private function ensureCatalogLoaded(): void
    {
        if ($this->store_movies === []) {
            $this->store_movies = $this->loadInitialMoviesCatalog();
        }
    }

    /**
     * Load movies from MovieListingService (same source as TableDemo), with a safe fallback
     * if the database is not seeded in an isolated unit test environment.
     *
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    private function loadInitialMoviesCatalog(): array
    {
        try {
            /** @var MovieListingService $service */
            $service = app(MovieListingService::class);
            $result = $service->paginate(page: 1, perPage: 20, sortField: 'title', sortDirection: 'asc');
            /** @var list<Movie> $movies */
            $movies = array_values($result['items']);

            if ($movies !== []) {
                $items = [];
                foreach ($movies as $movie) {
                    $title = t((string) $movie->title);
                    $genreRaw = $movie->genre->name ?? 'genre.unknown';
                    $genre = t((string) $genreRaw);
                    $year = (string) $movie->release_year;
                    $cast = (string) $movie->cast_members;

                    $items[] = [
                        'id' => $movie->id,
                        'label' => "{$title} ({$year})",
                        'subtitle' => "Reparto: {$cast}",
                        'icon' => $this->resolveGenreIcon((string) $genreRaw),
                        'badge' => $genre,
                        'badge_style' => 'primary',
                    ];
                }

                return $items;
            }
        } catch (Throwable) {
            // Fallback below when running in memory without DB migrations
        }

        return [
            [
                'id' => 1,
                'label' => 'Inception (2010)',
                'subtitle' => 'Reparto: Leonardo DiCaprio, Joseph Gordon-Levitt, Ellen Page',
                'icon' => '🚀',
                'badge' => 'Sci-Fi',
                'badge_style' => 'primary',
            ],
            [
                'id' => 2,
                'label' => 'The Dark Knight (2008)',
                'subtitle' => 'Reparto: Christian Bale, Heath Ledger, Aaron Eckhart',
                'icon' => '💥',
                'badge' => 'Action',
                'badge_style' => 'primary',
            ],
            [
                'id' => 3,
                'label' => 'The Matrix (1999)',
                'subtitle' => 'Reparto: Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss',
                'icon' => '🚀',
                'badge' => 'Sci-Fi',
                'badge_style' => 'primary',
            ],
            [
                'id' => 4,
                'label' => 'The Godfather (1972)',
                'subtitle' => 'Reparto: Marlon Brando, Al Pacino, James Caan',
                'icon' => '🕵️',
                'badge' => 'Crime',
                'badge_style' => 'warning',
            ],
            [
                'id' => 5,
                'label' => 'Interstellar (2014)',
                'subtitle' => 'Reparto: Matthew McConaughey, Anne Hathaway, Jessica Chastain',
                'icon' => '🚀',
                'badge' => 'Sci-Fi',
                'badge_style' => 'primary',
            ],
        ];
    }

    private function resolveGenreIcon(string $genreKey): string
    {
        return match ($genreKey) {
            'genre.sci-fi' => '🚀',
            'genre.action' => '💥',
            'genre.crime' => '🕵️',
            'genre.drama' => '🎭',
            'genre.fantasy' => '🧙',
            'genre.animation' => '🎨',
            'genre.romance' => '❤️',
            'genre.thriller' => '🔪',
            default => '🎬',
        };
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
        $labels = [];

        foreach ($this->store_movies as $movie) {
            if (in_array((string) $movie['id'], $selectedSet, true)) {
                $labels[] = $movie['label'];
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
