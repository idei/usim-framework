<?php

namespace App\UI\Screens;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;

class Home extends AbstractUIService
{
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container->shadow(0)->padding("0px");
        $container->add(
            UIBuilder::label('welcome_usim')
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
