<?php

use Idei\Usim\Enums\ContainerAppearance;
use Idei\Usim\UI;

it('supports semantic container appearance fluent helpers and enum values', function (
    string $method,
    ContainerAppearance $enum,
    string $expected
) {
    $fromHelper = UI::container("helper_{$expected}")->{$method}();
    $fromEnum = UI::container("enum_{$expected}")->appearance($enum);
    $fromString = UI::container("string_{$expected}")->appearance('  '.strtoupper($expected).'  ');

    expect($fromHelper->toJson()[$fromHelper->getId()]['appearance'])->toBe($expected)
        ->and($fromEnum->toJson()[$fromEnum->getId()]['appearance'])->toBe($expected)
        ->and($fromString->toJson()[$fromString->getId()]['appearance'])->toBe($expected);
})->with([
    'plain' => ['plain', ContainerAppearance::PLAIN, 'plain'],
    'subtle' => ['subtle', ContainerAppearance::SUBTLE, 'subtle'],
    'card' => ['card', ContainerAppearance::CARD, 'card'],
    'elevated' => ['elevated', ContainerAppearance::ELEVATED, 'elevated'],
    'highlight' => ['highlight', ContainerAppearance::HIGHLIGHT, 'highlight'],
    'contrast' => ['contrast', ContainerAppearance::CONTRAST, 'contrast'],
]);

it('falls back to card when an unknown container appearance string is provided', function () {
    $container = UI::container('unknown_appearance')->appearance('non-existent-style');

    expect($container->toJson()[$container->getId()]['appearance'])->toBe('card');
});

it('inherits all appearance helpers on Split containers', function () {
    $split = UI::split('split_highlight')->highlight();

    expect($split->toJson()[$split->getId()]['appearance'])->toBe('highlight');
});

it('applies semantic appearance variants from layoutDefinition', function (string $appearance) {
    $container = UI::container("layout_{$appearance}")->layoutDefinition([
        'appearance' => $appearance,
        'grid' => [
            'cols' => 1,
            'children' => ['lbl_item'],
        ],
    ]);

    $container->add(UI::newLabel('lbl_item')->text('Item'));

    expect($container->toJson()[$container->getId()]['appearance'])->toBe($appearance);
})->with(['plain', 'subtle', 'card', 'elevated', 'highlight', 'contrast']);

it('keeps frontend container/split JS, theme tokens, and published CSS synced for all appearances', function () {
    $containerJs = file_get_contents(base_path('packages/idei/usim/resources/assets/js/components/container/index.js'));
    $splitJs = file_get_contents(base_path('packages/idei/usim/resources/assets/js/components/split/index.js'));
    $componentsCss = file_get_contents(base_path('packages/idei/usim/resources/assets/css/ui-components.css'));
    $tokensCss = file_get_contents(base_path('packages/idei/usim/resources/assets/css/ui-theme-tokens.css'));
    $publishedCss = file_get_contents(public_path('vendor/idei/usim/css/ui-components.css'));
    $publishedContainerJs = file_get_contents(public_path('vendor/idei/usim/js/components/container/index.js'));

    foreach (['plain', 'subtle', 'card', 'elevated', 'highlight', 'contrast'] as $appearance) {
        expect($containerJs)->toContain("ui-container-{$appearance}")
            ->and($splitJs)->toContain("ui-container-{$appearance}")
            ->and($componentsCss)->toContain(".ui-container.ui-container-{$appearance}")
            ->and($publishedCss)->toContain(".ui-container.ui-container-{$appearance}");
    }

    expect($publishedContainerJs)->toBe($containerJs)
        ->and($tokensCss)->toContain('--usim-container-subtle-bg')
        ->and($tokensCss)->toContain('--usim-container-elevated-shadow')
        ->and($tokensCss)->toContain('--usim-container-highlight-accent')
        ->and($tokensCss)->toContain('--usim-container-contrast-bg');
});
