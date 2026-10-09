<?php

use App\UI\Screens\Auth\Login;
use Idei\Usim\Contracts\ScreenDiscoveryScannerInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\FileScreenDiscoveryScanner;
use Idei\Usim\Support\ScreenDiscoveryService;

it('returns empty array when scanning a non-existent path', function () {
    $scanner = new FileScreenDiscoveryScanner;
    $result = $scanner->scan('/path/to/non/existent/directory');

    expect($result)->toBeArray()->toBeEmpty();
});

it('discovers valid screens from the actual UI screens directory', function () {
    $scanner = new FileScreenDiscoveryScanner;
    $screens = $scanner->scan();

    expect($screens)->toBeArray();
    expect($screens)->not->toBeEmpty();

    foreach ($screens as $screenClass) {
        expect(class_exists($screenClass))->toBeTrue();
        expect(is_subclass_of($screenClass, Screen::class))->toBeTrue();
        $ref = new ReflectionClass($screenClass);
        expect($ref->isAbstract())->toBeFalse();
    }
});

it('allows ScreenDiscoveryService to be tested with a mocked scanner without touching the filesystem', function () {
    $mockScanner = Mockery::mock(ScreenDiscoveryScannerInterface::class);

    // Using an existing concrete screen class
    $concreteScreen = Login::class;

    $mockScanner->shouldReceive('scan')
        ->once()
        ->with('/custom/path', 'Custom\\Namespace')
        ->andReturn([$concreteScreen]);

    $service = new ScreenDiscoveryService($mockScanner);
    $manifest = $service->discover('/custom/path', 'Custom\\Namespace');

    expect($manifest)->toHaveKey($concreteScreen);
    expect($manifest[$concreteScreen])->toHaveKeys(['id_offset', 'route_path']);
    expect($manifest[$concreteScreen]['id_offset'])->toBeInt();
    expect($manifest[$concreteScreen]['route_path'])->toBe($concreteScreen::getRoutePath());
});

it('returns empty manifest when scanner finds no screens', function () {
    $mockScanner = Mockery::mock(ScreenDiscoveryScannerInterface::class);
    $mockScanner->shouldReceive('scan')
        ->once()
        ->andReturn([]);

    $service = new ScreenDiscoveryService($mockScanner);
    $manifest = $service->discover();

    expect($manifest)->toBeArray()->toBeEmpty();
});
