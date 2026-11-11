<?php

use Idei\Usim\UsimServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers the usim-docs publish tag pointing to docs/usim and ships docs in distribution', function () {
    $paths = ServiceProvider::pathsToPublish(UsimServiceProvider::class, 'usim-docs');

    $sourcePath = realpath(base_path('packages/idei/usim/docs'));
    $expectedDestination = base_path('docs/usim');

    expect($sourcePath)->not->toBeFalse()
        ->and($paths)->not->toBeEmpty();

    $resolvedSources = array_map(
        static fn (string $path): string|false => realpath($path),
        array_keys($paths)
    );

    expect($resolvedSources)->toContain($sourcePath)
        ->and(array_values($paths))->toContain($expectedDestination)
        ->and(is_file($sourcePath.'/README.md'))->toBeTrue()
        ->and(is_file($sourcePath.'/DECLARATIVE_LAYOUTS_AND_I18N.md'))->toBeTrue();

    $gitattributes = file_get_contents(base_path('packages/idei/usim/.gitattributes'));
    expect($gitattributes)->toBeString()
        ->and($gitattributes)->not->toContain('/docs export-ignore');
});
