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

        $hero = $homeConfig['hero'] ?? [];
        $container->add(
            UIBuilder::label('welcome')
                ->text($this->tr($hero['welcome_key'] ?? 'home.hero.welcome'))
                ->style('h1')
                ->center()
        );

        $container->add(
            UIBuilder::label('subtitle')
                ->text($this->tr($hero['subtitle_key'] ?? 'home.hero.subtitle'))
                ->style('h2')
                ->center()
        );

        $features = $homeConfig['features'] ?? [];
        $container->add(
            UIBuilder::label('features_title')
                ->text($this->tr($features['title_key'] ?? 'home.features.title'))
                ->style('h3')
                ->center()
        );

        $featuresContainer = UIBuilder::container('features')
            ->layout(LayoutType::HORIZONTAL)
            ->padding(20)
            ->gap((string) ($features['gap'] ?? '20px'))
            ->shadow(0)
            ->justifyContent('center')
            ->alignItems('center');

        foreach (($features['cards'] ?? []) as $cardConfig) {
            $featuresContainer->add($this->buildConfiguredCard($cardConfig));
        }

        $container->add($featuresContainer);

        $gettingStarted = $homeConfig['getting_started'] ?? [];
        $container->add(
            UIBuilder::label('getting_started')
                ->text($this->tr($gettingStarted['title_key'] ?? 'home.getting_started.title'))
                ->style('h2')
                ->center()
        );

        foreach (($gettingStarted['cards'] ?? []) as $cardConfig) {
            $container->add($this->buildConfiguredCard($cardConfig));
        }
    }

    private function buildConfiguredCard(array $cardConfig)
    {
        $name = (string) ($cardConfig['name'] ?? 'home_card_' . uniqid());
        $card = UIBuilder::card($name)
            ->title($this->tr((string) ($cardConfig['title_key'] ?? '')))
            ->description($this->tr((string) ($cardConfig['description_key'] ?? '')))
            ->theme((string) ($cardConfig['theme'] ?? 'primary'))
            ->elevation((string) ($cardConfig['elevation'] ?? 'medium'));

        if (!empty($cardConfig['style'])) {
            $card->style((string) $cardConfig['style']);
        }

        if (!empty($cardConfig['size'])) {
            $card->size((string) $cardConfig['size']);
        }

        if (!empty($cardConfig['image'])) {
            $imageBasePath = (string) config('ui-home.images.base_path', 'vendor/idei/usim/images');
            $imagePosition = (string) ($cardConfig['image_position'] ?? 'top');
            $imageAlt = $this->tr((string) ($cardConfig['image_alt_key'] ?? $cardConfig['title_key'] ?? ''));
            $card->image(asset(trim($imageBasePath, '/') . '/' . ltrim((string) $cardConfig['image'], '/')), $imagePosition, $imageAlt);
        }

        foreach (($cardConfig['actions'] ?? []) as $actionConfig) {
            $card->addAction(
                $this->tr((string) ($actionConfig['label_key'] ?? '')),
                (string) ($actionConfig['action'] ?? ''),
                (array) ($actionConfig['parameters'] ?? []),
                (string) ($actionConfig['style'] ?? 'primary')
            );
        }

        return $card;
    }

    private function tr(string $key): string
    {
        if ($key === '') {
            return '';
        }

        return __($key);
    }

    /**
     * Handler for viewing demos
     */
    public function onViewDemos(array $params): array
    {
        return [
            'action' => 'redirect',
            'url' => '/demo/demo-ui'
        ];
    }

    /**
     * Handler for viewing code examples
     */
    public function onViewCode(array $params): array
    {
        return [
            'action' => 'redirect',
            'url' => '/demo/form-demo'
        ];
    }

    /**
     * Handler for customization demo
     */
    public function onCustomize(array $params): array
    {
        return [
            'action' => 'redirect',
            'url' => '/demo/button-demo'
        ];
    }

    /**
     * Handler for viewing all demos
     */
    public function onViewAllDemos(array $params): array
    {
        return [
            'action' => 'redirect',
            'url' => '/demo/demo-ui'
        ];
    }

    /**
     * Handler for viewing documentation
     */
    public function onViewDocs(array $params): array
    {
        return [
            'action' => 'redirect',
            'url' => '/demo/table-demo'
        ];
    }
}
