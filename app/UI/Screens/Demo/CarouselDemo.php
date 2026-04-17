<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Screen;
use Idei\Usim\Components\CarouselBuilder;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UIBuilder;

class CarouselDemo extends Screen
{
    protected CarouselBuilder $manual_carousel;
    protected CarouselBuilder $auto_carousel;

    protected int $store_manual_index = 0;
    protected int $store_auto_index = 0;
    protected int $store_auto_timeout_ms = 5000;
    protected bool $store_auto_fullscreen = false;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $manualItems = $this->manualItems();
        $autoItems = $this->autoItems();

        $this->store_manual_index = $this->normalizeIndex($this->store_manual_index, count($manualItems));
        $this->store_auto_index = $this->normalizeIndex($this->store_auto_index, count($autoItems));

        $container
            ->title(t('screen.demo.carousel_demo.title'))
            ->maxWidth('980px')
            ->centerHorizontal()
            ->plain()
            ->padding('24px');

        $container->add(
            UIBuilder::label('carousel_demo_intro')
                ->text(t('screen.demo.carousel_demo.intro'))
                ->style('info')
        );

        $container->add(
            UIBuilder::label('manual_title')
                ->text(t('screen.demo.carousel_demo.manual_mode'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::carousel('manual_carousel')
                ->modeManual()
                ->loop(true)
                ->fullscreen(false)
                ->showPrev(true)
                ->showNext(true)
                ->items($manualItems)
                ->knownCount(count($manualItems))
                ->currentIndex($this->store_manual_index)
                ->currentMedia($manualItems[$this->store_manual_index])
                ->indicatorsBottom()
                ->nextAction('carousel_next')
                ->prevAction('carousel_prev')
        );

        $container->add(
            UIBuilder::label('auto_title')
                ->text(t('screen.demo.carousel_demo.auto_mode'))
                ->style('primary')
                ->marginTop('16px')
        );

        $container->add(
            UIBuilder::carousel('auto_carousel')
                ->modeAuto()
                ->autoplay(true)
                ->autoAction('carousel_tick')
                ->autoTimeoutMs($this->store_auto_timeout_ms)
                ->loop(true)
                ->fullscreen($this->store_auto_fullscreen)
                ->showPrev(false)
                ->showNext(false)
                ->knownCount(null)
                ->currentIndex($this->store_auto_index)
                ->currentMedia($autoItems[$this->store_auto_index])
                ->hideIndicators()
        );

        $buttons = UIBuilder::container('carousel_demo_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->gap('10px')
            ->marginTop('16px')
            ->shadow(0);

        $buttons->add(
            UIBuilder::button('btn_auto_fullscreen')
                ->label(t('screen.demo.carousel_demo.actions.toggle_auto_fullscreen'))
                ->style('secondary')
                ->action('toggle_auto_fullscreen')
        );

        $buttons->add(
            UIBuilder::button('btn_reset_carousel_demo')
                ->label(t('screen.demo.carousel_demo.actions.reset'))
                ->style('warning')
                ->action('reset_carousel_demo')
        );

        $container->add($buttons);
    }

    public function onCarouselNext(array $params): void
    {
        $carouselName = $params['carousel_name'] ?? 'manual_carousel';

        if ($carouselName !== 'manual_carousel') {
            return;
        }

        $items = $this->manualItems();
        $count = count($items);
        $this->store_manual_index = $this->normalizeIndex($this->store_manual_index + 1, $count);

        $this->manual_carousel
            ->currentIndex($this->store_manual_index)
            ->currentMedia($items[$this->store_manual_index]);
    }

    public function onCarouselPrev(array $params): void
    {
        $carouselName = $params['carousel_name'] ?? 'manual_carousel';

        if ($carouselName !== 'manual_carousel') {
            return;
        }

        $items = $this->manualItems();
        $count = count($items);
        $this->store_manual_index = $this->normalizeIndex($this->store_manual_index - 1, $count);

        $this->manual_carousel
            ->currentIndex($this->store_manual_index)
            ->currentMedia($items[$this->store_manual_index]);
    }

    public function onCarouselTick(array $params): void
    {
        $carouselName = $params['carousel_name'] ?? 'auto_carousel';

        if ($carouselName !== 'auto_carousel') {
            return;
        }

        $items = $this->autoItems();
        $count = count($items);
        $this->store_auto_index = $this->normalizeIndex($this->store_auto_index + 1, $count);

        $next = $items[$this->store_auto_index];
        $nextTimeout = (int) ($next['duration_ms'] ?? 5000);
        $this->store_auto_timeout_ms = max(1000, $nextTimeout);

        $this->auto_carousel
            ->currentIndex($this->store_auto_index)
            ->currentMedia($next)
            ->autoTimeoutMs($this->store_auto_timeout_ms)
            ->knownCount(null);
    }

    public function onToggleAutoFullscreen(array $params): void
    {
        $this->store_auto_fullscreen = !$this->store_auto_fullscreen;
        $this->auto_carousel->fullscreen($this->store_auto_fullscreen);
    }

    public function onResetCarouselDemo(array $params): void
    {
        $this->store_manual_index = 0;
        $this->store_auto_index = 0;
        $this->store_auto_timeout_ms = 5000;
        $this->store_auto_fullscreen = false;

        $manualItems = $this->manualItems();
        $autoItems = $this->autoItems();

        $this->manual_carousel
            ->currentIndex(0)
            ->currentMedia($manualItems[0]);

        $this->auto_carousel
            ->currentIndex(0)
            ->currentMedia($autoItems[0])
            ->autoTimeoutMs($this->store_auto_timeout_ms)
            ->fullscreen(false);
    }

    private function normalizeIndex(int $index, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return (($index % $count) + $count) % $count;
    }

    private function manualItems(): array
    {
        return [
            [
                'id' => 'm1',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screen.demo.carousel_demo.media.manual_image_1'),
            ],
            [
                'id' => 'm2',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screen.demo.carousel_demo.media.manual_image_2'),
            ],
            [
                'id' => 'm3',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screen.demo.carousel_demo.media.manual_image_3'),
            ],
        ];
    }

    private function autoItems(): array
    {
        return [
            [
                'id' => 'a1',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screen.demo.carousel_demo.media.auto_image_5s'),
                'duration_ms' => 5000,
            ],
            [
                'id' => 'a2',
                'kind' => 'video',
                'url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'mime' => 'video/mp4',
                'title' => t('screen.demo.carousel_demo.media.auto_video_5s'),
                'duration_ms' => 5000,
            ],
            [
                'id' => 'a3',
                'kind' => 'audio',
                'url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-audio/t-rex-roar.mp3',
                'mime' => 'audio/mpeg',
                'title' => t('screen.demo.carousel_demo.media.auto_audio_2s'),
                'duration_ms' => 2000,
            ],
        ];
    }
}
