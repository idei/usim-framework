<?php

namespace Tests\Feature;

use App\UI\Screens\Demo\ImageDemo;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
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

it('renders ImageDemo screen containing the Image component', function () {
    /** @var TestCase $this */
    $ui = uiScenario($this, ImageDemo::class, ['reset' => true]);

    $img = $ui->component('public_image');
    $img->expect('type')->toBe('image');
    $img->expect('action')->toBe('image');

    $ui->assertNoIssues();
});

it('fetches image data from ImageDemo getImage() via GET /api/ui-data', function () {
    /** @var TestCase $this */
    $screen = Screen::make(ImageDemo::class);
    $screen->render();

    $imgId = UIIdGenerator::generateFromName(ImageDemo::class, 'public_image');

    $response = $this->get("/api/ui-data?component_id={$imgId}&action=image");

    $response->assertOk();
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toMatch('/image\/(jpeg|svg\+xml|png)/');
    expect(strlen($response->getContent()))->toBeGreaterThan(50);
});
