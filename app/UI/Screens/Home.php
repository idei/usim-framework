<?php

namespace App\UI\Screens;

use Idei\Usim\UI;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;

class Home extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->shadow(0)->padding("0px");
        $container->add(
            UI::label('welcome_usim')
                ->html('welcome-usim')
                ->width('100%')
        );
    }

    /**
     * Handler for viewing demos
     */
    public function onViewDemos(array $params): void
    {
        $this->redirect('/demo/demo-ui');
    }

    /**
     * Handler for viewing code examples
     */
    public function onViewCode(array $params): void
    {
        $this->redirect('/demo/form-demo');
    }

    /**
     * Handler for customization demo
     */
    public function onCustomize(array $params): void
    {
        $this->redirect('/demo/button-demo');
    }

    /**
     * Handler for viewing all demos
     */
    public function onViewAllDemos(array $params): void
    {
        $this->redirect('/demo/demo-ui');
    }

    /**
     * Handler for viewing documentation
     */
    public function onViewDocs(array $params): void
    {
        $this->redirect('/demo/table-demo');
    }
}
