<?php

namespace App\UI\Screens\Demo;

use App\Services\Movie\MovieListingService;
use App\UI\Components\DataTable\MovieTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Override;

class TableDemo extends Screen
{
    protected Table $movies_table;
    protected Input $search_movies;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->plain()
            ->centerHorizontal()
            ->gap(Spacing::px(5));

        $label = UI::label()
            ->text(t('screen.demo.table_demo.title'))
            ->style('h2');

        $table = UI::table('movies_table')
            ->title(t('screen.demo.table_demo.table_title'))
            ->sortedBy('title')
            ->pagination(7)
            ->dataModel(MovieTableModel::class);

        $table->bodyOverflowX('hidden');
        $table->bodyOverflowY('auto');
        $table->selectionMode(SelectionMode::SINGLE);
        $table->align('center');

        $container
            ->maxWidth($table->getWidth())
            ->add($label)
            ->add($this->buildToolbar())
            ->add($table);
    }

    #[Override]
    protected function postLoadUI(): void
    {
        $this->search_movies->value($this->movies_table->getSearchTerm());
    }

    private function buildToolbar(): Container
    {
        $toolbar = UI::container('movies_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->shadow(0)
            ->gap(Spacing::px(16));

        $search = UI::input('search_movies')
            ->placeholder(t('screen.demo.table_demo.search_placeholder'))
            ->width(Size::px(300))
            ->autocomplete('off')
            ->onInput('search_input_typed', [])
            ->debounce(500);

        $toolbar->add($search);
        return $toolbar;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onMoviesTableColumnClicked(array $params): void
    {
        $column = $params['sort_by'] ?? null;
        if (!is_string($column) || $column === '') {
            return;
        }

        $this->movies_table->sortedBy($column);
        $this->movies_table->page(1);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchInputTyped(array $params): void
    {
        $raw = $params['value'] ?? null;
        $value = is_string($raw) ? $raw : '';
        $this->movies_table->setSearchTerm($value);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onChangePage(array $params): void
    {
        $rawPage = $params['page'] ?? 1;
        $page = is_int($rawPage) ? $rawPage : 1;
        $this->movies_table->page($page);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onMoviesTableRowClicked(array $params): void
    {
        $modelId = $params['model_id'] ?? null;
        if ($modelId === null || $modelId === '') {
            return;
        }
        if (!is_int($modelId) && !is_string($modelId)) {
            return;
        }

        // Always persist selected row exactly as received (supports int and string IDs).
        $this->movies_table->select($modelId);

        if (!is_numeric((string) $modelId)) {
            return;
        }

        $movieId = (int) $modelId;
        if ($movieId <= 0) {
            return;
        }

        /** @var \App\Models\Movie $movie */
        $movie = app(MovieListingService::class)->findById($movieId);

        $this->toast(t('screen.demo.table_demo.row_clicked_toast', [
            'name' => t($movie->title),
        ]));
    }

}
