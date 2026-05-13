<?php

namespace App\UI\Screens\Demo;

use App\Services\Movie\MovieListingService;
use App\UI\Components\DataTable\MovieTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Override;

class TableDemo extends Screen
{
    protected Table $movies_table;
    protected Input $search_movies;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->plain()
            ->centerHorizontal()
            ->gap('5px');

        $label = UI::label()
            ->text(t('screen.demo.table_demo.title'))
            ->style('h2');

        $table = UI::table('movies_table')
            ->title(t('screen.demo.table_demo.table_title'))
            ->sortedBy('title')
            ->pagination(10)
            ->dataModel(MovieTableModel::class)
            ->bodyOverflowX('hidden')
            ->bodyOverflowY('auto')
            ->align('center');

        $container
            ->maxWidth($table->width())
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
            ->gap("12px");

        $search = UI::input('search_movies')
            ->placeholder(t('screen.demo.table_demo.search_placeholder'))
            ->width('300px')
            ->autocomplete('off')
            ->onInput('search_input_typed', [])
            ->debounce(500);

        $toolbar->add($search);
        return $toolbar;
    }

    public function onMoviesTableColumnClicked(array $params): void
    {
        $column = $params['sort_by'] ?? null;
        if (!$column) {
            return;
        }

        $this->movies_table->sortedBy($column);
        $this->movies_table->page(1);
    }

    public function onSearchInputTyped(array $params): void
    {
        $value = (string) ($params['value'] ?? '');
        $this->movies_table->setSearchTerm($value);
    }

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->movies_table->page($page);
    }

    public function onMoviesTableRowClicked(array $params): void
    {
        $movieId = isset($params['model_id']) ? (int) $params['model_id'] : 0;
        if ($movieId <= 0) {
            return;
        }

        $movie = app(MovieListingService::class)->findById($movieId);

        $this->toast(t($movie->title));
    }

}
