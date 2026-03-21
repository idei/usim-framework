<?php

namespace App\UI\Screens;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;

class Home extends AbstractUIService
{
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $homeConfig = config('ui-home', []);

        $container
            ->layout(LayoutType::VERTICAL)
            ->shadow(0)
            ->justifyContent('center')
            ->alignItems('center')
            ->padding(20);

        $container->add(
            UIBuilder::label('welcome')->h1()->center()->text('home.hero.welcome')
        );

        $container->add(
            UIBuilder::label('subtitle')->h2()->center()->text('home.hero.subtitle')
        );

        $container->add(
            UIBuilder::label('features_title')->h3()->center()->text('home.features.title')
        );

        $featuresContainer = UIBuilder::container('features')
            ->layout(LayoutType::HORIZONTAL)
            ->padding(20)
            ->gap('20px')
            ->shadow(0)
            ->justifyContent('center')
            ->alignItems('center');

        $features = $homeConfig['features'] ?? [];
        $featuresCards = $features['cards'] ?? [];

        foreach ($featuresCards as $cardConfig) {
            $featuresContainer->add($this->buildConfiguredCard($cardConfig));
        }

        $container->add($featuresContainer);

        $container->add(
            UIBuilder::label('getting_started')->h2()->center()->text('home.getting_started.title')
        );

        $gettingStarted = $homeConfig['getting_started'] ?? [];
        $gettingStartedCards = $gettingStarted['cards'] ?? [];

        foreach ($gettingStartedCards as $cardConfig) {
            $container->add($this->buildConfiguredCard($cardConfig));
        }
    }

    private function buildConfiguredCard(array $cardConfig)
    {
        $name = $cardConfig['name'] ?? 'home_card_' . uniqid();
        $card = UIBuilder::card($name)
            ->title($cardConfig['title_key'])
            ->description($cardConfig['description_key'])
            ->theme($cardConfig['theme'] ?? 'primary')
            ->shadow(1);

        if (!empty($cardConfig['style'])) {
            $card->style($cardConfig['style']);
        }

        if (!empty($cardConfig['size'])) {
            $card->size($cardConfig['size']);
        }

        if (!empty($cardConfig['image'])) {
            $imageBasePath = (string) config('ui-home.images.base_path', 'vendor/idei/usim/images');
            $imagePosition = (string) ($cardConfig['image_position'] ?? 'top');
            $imageAlt = $cardConfig['image_alt_key'] ?? $cardConfig['title_key'] ?? '';
            $card->image(asset(trim($imageBasePath, '/') . '/' . ltrim((string) $cardConfig['image'], '/')), $imagePosition, $imageAlt);
        }

        foreach (($cardConfig['actions'] ?? []) as $actionConfig) {
            $card->addAction(
                $actionConfig['label_key'] ?? '',
                (string) ($actionConfig['action'] ?? ''),
                (array) ($actionConfig['parameters'] ?? []),
                (string) ($actionConfig['style'] ?? 'primary')
            );
        }

        return $card;
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
