<?php

namespace Tests\Feature;

use App\UI\Screens\Demo\ImageDemo;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Services\ImagePlaceholders\PicsumImagePlaceholderGenerator;
use Idei\Usim\Services\ImagePlaceholders\SvgImagePlaceholderGenerator;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use RuntimeException;
use Tests\TestCase;

it('builds Image component with fluent API correctly', function () {
    $img = UI::image('avatar')
        ->src('https://example.com/avatar.jpg')
        ->alt('User Avatar')
        ->width(Size::px(120))
        ->height(Size::px(120))
        ->circular()
        ->shadow()
        ->border()
        ->lazy(false)
        ->caption('Profile Photo');

    $config = $img->toJson()[$img->getId()];

    expect($config['type'])->toBe('image');
    expect($config['name'])->toBe('avatar');
    expect($config['src'])->toBe('https://example.com/avatar.jpg');
    expect($config['alt'])->toBe('User Avatar');
    expect($config['width'])->toBe('120px');
    expect($config['height'])->toBe('120px');
    expect($config['border_radius'])->toBe('50%');
    expect($config['shadow'])->toBeTrue();
    expect($config['border'])->toBeTrue();
    expect($config['lazy'])->toBeFalse();
    expect($config['caption'])->toBe('Profile Photo');
});

it('generates a default SVG image using the configured aspect ratio and caption', function () {
    $img = UI::image('post_image')
        ->width(Size::px(640))
        ->placeholderAspect(AspectRatio::_16_9)
        ->placeholderId(42)
        ->placeholderColors('#123456', '#abcdef')
        ->alt('Post & news');

    $config = $img->toJson()[$img->getId()];
    $src = $config['src'] ?? null;
    if (! is_string($src)) {
        throw new RuntimeException('Expected the image placeholder source to be a string.');
    }
    $svg = base64_decode(substr($src, strlen('data:image/svg+xml;base64,')), true);
    if (! is_string($svg)) {
        throw new RuntimeException('Expected the image placeholder source to contain valid base64.');
    }

    expect($src)->toStartWith('data:image/svg+xml;base64,');
    expect($svg)->toContain('width="640" height="360"');
    expect($svg)->toContain('Post &amp; news');
    expect($svg)->toContain('fill="#123456"');
    expect($svg)->toContain('fill="#abcdef"');
    expect($config)->not->toHaveKey('placeholder_aspect_ratio');
    expect($config)->not->toHaveKey('placeholder_enabled');
    expect($config)->not->toHaveKey('placeholder_id');
    expect($config)->not->toHaveKey('placeholder_background_color');
    expect($config)->not->toHaveKey('placeholder_text_color');
});

it('wraps and centers long placeholder captions within the image bounds', function () {
    $img = UI::image('portrait_placeholder')
        ->height(Size::px(320))
        ->placeholderAspect(AspectRatio::_9_16)
        ->alt('Custom background and text colors stay centered');

    $config = $img->toJson()[$img->getId()];
    $src = $config['src'] ?? null;
    if (! is_string($src)) {
        throw new RuntimeException('Expected the image placeholder source to be a string.');
    }

    $svg = base64_decode(substr($src, strlen('data:image/svg+xml;base64,')), true);
    if (! is_string($svg)) {
        throw new RuntimeException('Expected the image placeholder source to contain valid base64.');
    }

    expect($svg)->toContain('text-anchor="middle"');
    expect(substr_count($svg, '<tspan x="50%"'))->toBeGreaterThan(1);
    expect($svg)->toContain('Custom');
    expect($svg)->toContain('centered');
});

it('uses a stable Picsum seed and calculates dimensions from height', function () {
    $originalGenerator = config('usim.images.placeholder.generator');
    config(['usim.images.placeholder.generator' => PicsumImagePlaceholderGenerator::class]);

    try {
        $img = UI::image('post_image')
            ->height(Size::px(360))
            ->placeholderAspect(AspectRatio::_16_9)
            ->placeholderId(42);

        $config = $img->toJson()[$img->getId()];

        expect($config['src'])->toBe('https://picsum.photos/seed/12342/640/360');
    } finally {
        config(['usim.images.placeholder.generator' => $originalGenerator]);
    }
});

it('keeps explicit sources and uses placeholders while async image actions load', function () {
    $explicit = UI::image('explicit_image')
        ->src('https://example.com/image.jpg')
        ->placeholderAspect(AspectRatio::_16_9)
        ->toJson();
    $explicitConfig = array_values($explicit)[0] ?? null;

    $async = UI::image('async_image')
        ->action('load_image')
        ->placeholderAspect(AspectRatio::_16_9)
        ->toJson();
    $asyncConfig = array_values($async)[0] ?? null;
    if (! is_array($explicitConfig) || ! is_array($asyncConfig)) {
        throw new RuntimeException('Expected serialized image component configurations.');
    }

    expect($explicitConfig['src'])->toBe('https://example.com/image.jpg');
    expect($asyncConfig['action'])->toBe('load_image');
    expect($asyncConfig['src'])->toStartWith('data:image/svg+xml;base64,');
});

it('resolves relative image filenames through the configured storage directory', function () {
    $component = UI::image('stored_image')
        ->src('post-cover.jpg')
        ->storage('uploads/images');
    $config = $component->toJson()[$component->getId()];

    expect($config['src'])->toBe(url('/files/uploads/images/post-cover.jpg'));
    expect($config)->not->toHaveKey('storage_path');
    expect($component->getDirtyKeys())->not->toContain('storage_path');
});

it('leaves absolute image sources unchanged when storage is configured', function () {
    $remote = UI::image('remote_image')
        ->src('https://cdn.example.com/post-cover.jpg')
        ->storage('uploads/images')
        ->toJson();
    $rootRelative = UI::image('root_image')
        ->src('/images/post-cover.jpg')
        ->storage('uploads/images')
        ->toJson();

    expect(array_values($remote)[0]['src'])->toBe('https://cdn.example.com/post-cover.jpg');
    expect(array_values($rootRelative)[0]['src'])->toBe('/images/post-cover.jpg');
});

it('renders ImageDemo screen containing the Image component', function () {
    /** @var TestCase $this */
    $originalGenerator = config('usim.images.placeholder.generator');
    config(['usim.images.placeholder.generator' => SvgImagePlaceholderGenerator::class]);

    try {
        $ui = uiScenario($this, ImageDemo::class, ['reset' => true]);

        $img = $ui->component('public_image');
        $img->expect('type')->toBe('image');
        $img->expect('action')->toBe('image');
        $img->expect('src')->toStartWith('data:image/svg+xml;base64,');

        $landscape = $ui->component('placeholder_landscape');
        $landscape->expect('width')->toBe('500px');
        $landscape->expect('src')->toStartWith('data:image/svg+xml;base64,');

        $portrait = $ui->component('placeholder_portrait');
        $portrait->expect('height')->toBe('320px');
        $portrait->expect('src')->toStartWith('data:image/svg+xml;base64,');

        expect($ui->component('placeholder_landscape_title')->data()['text'])
            ->toBe(t('screens.demo.image_demo.landscape_title'));
        expect($ui->component('placeholder_portrait_title')->data()['text'])
            ->toBe(t('screens.demo.image_demo.portrait_title'));

        $ui->assertNoIssues();
    } finally {
        config(['usim.images.placeholder.generator' => $originalGenerator]);
    }
});

it('fetches image data from ImageDemo getImage() via GET /api/ui-data', function () {
    /** @var TestCase $this */
    $screen = Screen::make(ImageDemo::class);
    $screen->render();

    $imgId = UIIdGenerator::generateFromName(ImageDemo::class, 'public_image');

    $response = $this->get("/api/ui-data?component_id={$imgId}&action=image");

    $response->assertOk();
    $contentType = $response->headers->get('Content-Type');
    $content = $response->getContent();
    expect($contentType)->toMatch('/image\/(jpeg|svg\+xml|png)/');
    if (! is_string($content)) {
        throw new RuntimeException('Expected image response content.');
    }
    expect(strlen($content))->toBeGreaterThan(50);
});
