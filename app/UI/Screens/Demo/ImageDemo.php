<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ImageDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Image $public_image;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $card = UI::container('image_demo_card')
            ->card()
            ->alignItems('center')
            ->alignContent('center')
            ->centerHorizontal()
            ->padding(Spacing::px(5))
            ->gap(Spacing::px(5));

        $card->add(
            UI::label('demo_title')
                ->text(t('screens.demo.image_demo.title'))
                ->style('h2')
        );

        $card->add(
            UI::label('demo_subtitle')
                ->text(t('screens.demo.image_demo.subtitle'))
                ->textAlign('center')
                ->width(Size::px(550))
        );

        $this->public_image = UI::image('public_image')
            ->action('image')
            ->width(Size::px(500))
            ->placeholderAspect(AspectRatio::_16_9)
            ->placeholderId('image-demo-dynamic')
            ->rounded(8)
            ->border()
            ->caption(t('screens.demo.image_demo.caption'))
            ->alt(t('screens.demo.image_demo.caption'));

        $card->add($this->public_image);

        $card->add(
            UI::label('placeholder_landscape_title')
                ->text(t('screens.demo.image_demo.landscape_title'))
                ->style('h3')
        );

        $card->add(
            UI::image('placeholder_landscape')
                ->width(Size::px(500))
                ->placeholderAspect(AspectRatio::_16_9)
                ->placeholderId('image-demo-landscape')
                ->alt(t('screens.demo.image_demo.landscape_caption'))
                ->rounded(8)
                ->border()
        );

        $card->add(
            UI::label('placeholder_portrait_title')
                ->text(t('screens.demo.image_demo.portrait_title'))
                ->style('h3')
        );

        $card->add(
            UI::image('placeholder_portrait')
                ->height(Size::px(320))
                ->placeholderAspect(AspectRatio::_9_16)
                ->placeholderId('image-demo-portrait')
                ->placeholderColors('#7c3aed', '#ffffff')
                ->alt(t('screens.demo.image_demo.portrait_caption'))
                ->rounded(8)
                ->border()
        );

        $container->add($card);
    }

    /**
     * Data query handler invoked via GET /api/ui-data?component_id={id}&action=image
     * This method is called when the Image component needs to fetch its data asynchronously.
     *
     * @param  array<string, string>  $params  The query parameters from the request, including 'seed' for image variation
     * @return Response|string The image response or fallback SVG if the request fails
     */
    public function getImage(array $params = []): Response|string
    {
        $seed = (string) ($params['seed'] ?? rand(1, 1000));

        $url = "https://picsum.photos/seed/{$seed}/500/310";

        try {
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type') ?: 'image/jpeg';

                return response($response->body(), 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                ]);
            }
        } catch (\Throwable $e) {
            // Fallback gracefully below
        }

        // Offline / Fallback SVG Image
        $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="500" height="330" viewBox="0 0 500 330">
            <defs>
                <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#1d4ed8;stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="500" height="330" fill="url(#grad)" rx="12" />
            <circle cx="250" cy="130" r="45" fill="rgba(255,255,255,0.2)" />
            <polygon points="210,240 290,240 250,170" fill="rgba(255,255,255,0.4)" />
            <polygon points="260,240 330,240 295,190" fill="rgba(255,255,255,0.3)" />
            <text x="250" y="275" font-family="sans-serif" font-size="18" fill="#ffffff" font-weight="bold" text-anchor="middle">
                USIM Framework - Image Demo
            </text>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
        ]);
    }
}
