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
                    ->text('Movies Table Demo')
                    ->style('h2')
            );

        $table = UI::table('movies_table')
            ->title('Famous Movies')
            ->pagination(10)
            ->dataModel(MovieTableModel::class)
            ->align('center')
            ->width('1200px')
            ->rowMinHeight(45);

        $container->add($table);
    }

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->movies_table->page($page);
    }

}
