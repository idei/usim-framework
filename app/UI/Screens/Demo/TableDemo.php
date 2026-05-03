<?php

namespace App\UI\Screens\Demo;

use App\UI\Components\DataTable\MovieTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Table;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class TableDemo extends Screen
{
    protected Table $movies_table;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->plain()
            ->add(
                UI::label()
                    ->text(t('screen.demo.table_demo.title'))
                    ->style('h2')
            );

        $table = UI::table('movies_table')
            ->title(t('screen.demo.table_demo.table_title'))
            ->pagination(7)
            ->sortedBy('title')
            ->dataModel(MovieTableModel::class)
            ->align('center')
            ->width('710px')
            ->rowMinHeight(50);

        $container->add($table);
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

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->movies_table->page($page);
    }

}
