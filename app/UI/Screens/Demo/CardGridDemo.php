<?php

namespace App\UI\Screens\Demo;

use App\Services\Movie\MovieCardGridService;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Select;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;

/**
 * CardGrid Demo Screen
 *
 * Showcases the responsive CardGrid component wired to a CardListable service
 * (MovieCardGridService) via .layout.yaml:
 * - Responsive left-to-right, top-to-bottom CSS grid with automatic breakpoints
 * - Integrated toolbar: debounced search, select/multi_select/chips/toggle filters, sorting
 * - Classic numbered pagination ('pages') and incremental 'load_more' mode switchable live
 * - Standardized client-side cards with media, badges, metrics, attributes, tags, and actions (+ ⋮ overflow)
 */
class CardGridDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $card_grid_demo_title;

    protected NewLabel $card_grid_demo_intro;

    protected Select $sel_pagination_mode;

    protected Select $sel_card_layout;

    protected Select $sel_density;

    protected CardGrid $movies_grid;

    protected string $store_pagination_mode = 'pages';

    protected string $store_card_layout = 'vertical';

    protected string $store_density = 'comfortable';

    protected function startingUI(): Container
    {
        $this->sel_pagination_mode
            ->options([
                ['value' => 'pages', 'label' => $this->t('.pagination_mode.options.pages')],
                ['value' => 'load_more', 'label' => $this->t('.pagination_mode.options.load_more')],
            ])
            ->value($this->store_pagination_mode);

        $this->sel_card_layout
            ->options([
                ['value' => 'vertical', 'label' => $this->t('.card_layout.options.vertical')],
                ['value' => 'horizontal', 'label' => $this->t('.card_layout.options.horizontal')],
            ])
            ->value($this->store_card_layout);

        $this->sel_density
            ->options([
                ['value' => 'compact', 'label' => $this->t('.density.options.compact')],
                ['value' => 'comfortable', 'label' => $this->t('.density.options.comfortable')],
                ['value' => 'spacious', 'label' => $this->t('.density.options.spacious')],
            ])
            ->value($this->store_density);

        $this->movies_grid
            ->paginationMode($this->store_pagination_mode)
            ->cardLayout($this->store_card_layout)
            ->density($this->store_density)
            ->sortOptions([
                ['field' => 'title', 'label' => 'Título'],
                ['field' => 'rating', 'label' => 'Puntaje IMDb'],
                ['field' => 'year', 'label' => 'Año'],
                ['field' => 'runtime', 'label' => 'Duración'],
            ])
            ->defaultSort('rating', 'desc')
            ->addSelectFilter('genre', 'Género', [
                'sci-fi' => 'Ciencia Ficción',
                'drama' => 'Drama',
                'action' => 'Acción',
                'thriller' => 'Thriller',
                'animation' => 'Animación',
            ], 'Género: Todos')
            ->addMultiSelectFilter('tags', 'Etiquetas', [
                'IMAX' => 'IMAX',
                'Premiada' => 'Premiada',
                'Clásico' => 'Clásico',
                '4K' => '4K',
                'Familiar' => 'Familiar',
                'Estreno' => 'Estreno',
            ], 'Etiquetas')
            ->addToggleFilter('featured', 'Solo destacadas')
            ->addChipsFilter('decade', 'Década', [
                '1970' => '70s',
                '1990' => '90s',
                '2000' => '2000s',
                '2010' => '2010s',
                '2020' => '2020s',
            ]);

        return $this->layoutContainer();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onPaginationModeChanged(array $params): void
    {
        $raw = $params['value'] ?? 'pages';
        $mode = is_string($raw) && $raw === 'load_more' ? 'load_more' : 'pages';
        $this->store_pagination_mode = $mode;
        $this->sel_pagination_mode->value($mode);
        $this->movies_grid->paginationMode($mode)->page(1)->reload();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCardLayoutChanged(array $params): void
    {
        $raw = $params['value'] ?? 'vertical';
        $layout = is_string($raw) && $raw === 'horizontal' ? 'horizontal' : 'vertical';
        $this->store_card_layout = $layout;
        $this->sel_card_layout->value($layout);
        $this->movies_grid
            ->cardLayout($layout)
            ->minCardWidth($layout === 'horizontal' ? '420px' : '270px');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onDensityChanged(array $params): void
    {
        $raw = $params['value'] ?? 'comfortable';
        $density = is_string($raw) && in_array($raw, ['compact', 'comfortable', 'spacious'], true)
            ? $raw
            : 'comfortable';
        $this->store_density = $density;
        $this->sel_density->value($density);
        $this->movies_grid->density($density);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onViewMovie(array $params): void
    {
        $movieId = $this->extractMovieId($params);
        $movie = app(MovieCardGridService::class)->findById($movieId);

        if ($movie === null) {
            $this->toast('Película no encontrada', 'warning');

            return;
        }

        $this->toast(
            "{$movie['icon']} {$movie['title']} ({$movie['year']}) — Dir. {$movie['director']} • ★ {$movie['rating']}",
            'info'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onToggleFavorite(array $params): void
    {
        $movieId = $this->extractMovieId($params);

        /** @var MovieCardGridService $service */
        $service = app(MovieCardGridService::class);
        $movie = $service->findById($movieId);
        if ($movie === null) {
            return;
        }

        $added = $service->toggleFavorite($movieId);
        $card = $this->movies_grid->card($movieId);
        $patch = $service->buildFavoritePatch($movieId, $added);

        if ($card !== null && $patch !== null) {
            // Pure USIM diffing: mutating the child ItemCard emits ONLY the changed keys
            // for this single ItemCard via ComponentDiffer (zero diff on CardGrid or siblings).
            $card->patch($patch);
        } else {
            $this->movies_grid->reload();
        }

        $this->toast(
            $added
                ? "★ \"{$movie['title']}\" agregada a tus favoritas"
                : "\"{$movie['title']}\" quitada de tus favoritas",
            $added ? 'success' : 'info'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onShareMovie(array $params): void
    {
        $movieId = $this->extractMovieId($params);
        $movie = app(MovieCardGridService::class)->findById($movieId);

        if ($movie !== null) {
            $this->toast("🔗 Enlace listo para compartir: {$movie['title']}", 'success');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onReportMovie(array $params): void
    {
        $movieId = $this->extractMovieId($params);
        $movie = app(MovieCardGridService::class)->findById($movieId);

        if ($movie !== null) {
            $this->toast("⚠️ Reporte enviado para la ficha de \"{$movie['title']}\"", 'warning');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function extractMovieId(array $params): int
    {
        $raw = $params['movie_id'] ?? $params['id'] ?? null;

        return is_numeric($raw) ? (int) $raw : 0;
    }
}
