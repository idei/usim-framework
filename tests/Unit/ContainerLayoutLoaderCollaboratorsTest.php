<?php

declare(strict_types=1);

use App\UI\Screens\Demo\FormDemo;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Support\ContainerLayoutLoader;
use Idei\Usim\Support\LayoutLoader\GridTrackNormalizer;
use Idei\Usim\Support\LayoutLoader\LayoutApplicationContext;
use Idei\Usim\UI;

it('infers numeric slot order and applies legacy grid items rules and array spans', function () {
    $container = UI::container('legacy_grid')->layoutDefinition([
        'grid' => [
            'cols' => 'repeat(3, 1fr)',
            'default_span' => 'full',
            'items' => [
                1 => ['span' => [2, 'full'], 'row_height' => 120],
                '2' => ['span' => [1, 2], 'at' => [3, 1]],
                'named_item' => ['span' => [2, 1], 'align' => 'center'],
                'invalid_entry' => 'ignored_non_array',
            ],
        ],
    ]);

    $first = UI::newLabel()->text('First')->slot(1);
    $second = UI::newLabel()->text('Second')->slot('2');
    $named = UI::button('named_item')->label('Named');

    // Add out of order with no explicit `children:` list so inferNumericSlotOrder activates
    $container->addMany([$second, $named, $first]);

    $json = $container->toJson();

    expect($json[$container->getId()]['grid_template_columns'])->toBe('repeat(3, 1fr)')
        ->and($json[$container->getId()]['grid_template_rows'])->toBe('120px auto')
        ->and($json[$first->getId()]['_order'])->toBe(1)
        ->and($json[$first->getId()]['grid_column'])->toBe('span 3')
        ->and($json[$first->getId()]['grid_row'])->toBe('span 2')
        ->and($json[$second->getId()]['_order'])->toBe(2)
        ->and($json[$second->getId()]['grid_column'])->toBe('1 / span 2')
        ->and($json[$second->getId()]['grid_row'])->toBe('3')
        ->and($json[$named->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$named->getId()]['grid_row'])->toBe('span 2')
        ->and($json[$named->getId()]['align_self'])->toBe('center');
});

it('auto-names unnamed grid children from explicit string slots and positional rules', function () {
    $container = UI::container('autoname_grid')->layoutDefinition([
        'grid' => [
            'cols' => '2',
            'children' => [
                'pos_first',
                'explicit_second',
            ],
        ],
    ]);

    $unnamedPositional = UI::newLabel()->text('Positional');
    $unnamedExplicit = UI::button()->label('Explicit')->slot('explicit_second');

    $container->addMany([$unnamedPositional, $unnamedExplicit]);
    $container->toJson();

    expect($unnamedPositional->getName())->toBe('pos_first')
        ->and($unnamedExplicit->getName())->toBe('explicit_second');
});

it('resolves column counts, spans, and track lists across string, numeric, array, and fallback inputs', function () {
    $normalizer = new GridTrackNormalizer;

    expect($normalizer->resolveColumnCount(4))->toBe(4)
        ->and($normalizer->resolveColumnCount(['1fr', '2fr', 'auto']))->toBe(3)
        ->and($normalizer->resolveColumnCount('3'))->toBe(3)
        ->and($normalizer->resolveColumnCount('repeat(5, minmax(0, 1fr))'))->toBe(5)
        ->and($normalizer->resolveColumnCount('200px 1fr auto'))->toBe(3)
        ->and($normalizer->resolveColumnCount(null))->toBe(1);

    expect($normalizer->resolveDefaultColSpan('full', 4))->toBe(4)
        ->and($normalizer->resolveDefaultColSpan('2', 4))->toBe(2)
        ->and($normalizer->resolveDefaultColSpan(null, 4))->toBe(1);

    expect($normalizer->resolveSpan(null, 2, 4))->toBe([1, 2])
        ->and($normalizer->resolveSpan('full', 2, 4))->toBe([1, 4])
        ->and($normalizer->resolveSpan('3', 2, 4))->toBe([1, 3])
        ->and($normalizer->resolveSpan([2, 'full'], 1, 4))->toBe([2, 4])
        ->and($normalizer->resolveSpan([3, 2], 1, 4))->toBe([3, 2])
        ->and($normalizer->resolveSpan(['bad', 'bad'], 2, 4))->toBe([1, 2])
        ->and($normalizer->resolveSpan(false, 2, 4))->toBe([1, 2]);

    expect($normalizer->normalizeTrackList(3))->toBe('repeat(3, 1fr)')
        ->and($normalizer->normalizeTrackList('2'))->toBe('repeat(2, 1fr)')
        ->and($normalizer->normalizeTrackList('1fr auto'))->toBe('1fr auto')
        ->and($normalizer->normalizeTrackList([200, '1fr', '300']))->toBe('200px 1fr 300px')
        ->and($normalizer->normalizeTrackList(false))->toBe('');
});

it('auto-hydrates direct children of non-grid containers without explicit children rules when i18n auto is enabled', function () {
    $container = UI::container('auto_hydrate_plain')->layoutDefinition([
        'i18n' => 'auto',
    ], callingContext: FormDemo::class);

    $inputName = UI::input('input_name');
    $btnSubmit = UI::button('btn_submit');
    $container->addMany([$inputName, $btnSubmit]);

    $json = $container->toJson();

    expect($json[$inputName->getId()]['label'])->toBe(t('screens.demo.form_demo.name.label'))
        ->and($json[$btnSubmit->getId()]['label'])->toBe(t('screens.demo.form_demo.actions.submit'));

    // Also verifies empty translation tree early-return branch cleanly
    $noTreeContainer = UI::container('no_tree')->layoutDefinition([
        'i18n' => 'auto',
    ], callingContext: 'NonExistentScreenClass');
    $noTreeContainer->add(UI::input('input_x'));
    expect($noTreeContainer->toJson())->toBeArray();
});

it('exposes facade helpers loadFile, resolvePath, extractExternalLayoutReference, resolveComponentClassForSlot, and normalizeChildEntry', function () {
    $path = ContainerLayoutLoader::resolvePath('app/UI/Screens/Demo/form-demo.layout.yaml');
    $definition = ContainerLayoutLoader::loadFile('app/UI/Screens/Demo/form-demo.layout.yaml');

    expect($path)->toEndWith('form-demo.layout.yaml')
        ->and($definition)->toBeArray()
        ->and($definition)->toHaveKey('grid');

    expect(ContainerLayoutLoader::extractExternalLayoutReference(['include' => 'partials/a']))->toBe('partials/a')
        ->and(ContainerLayoutLoader::extractExternalLayoutReference(['layout' => 'vertical']))->toBeNull()
        ->and(ContainerLayoutLoader::extractExternalLayoutReference(['layout' => 'custom.layout.yaml']))->toBe('custom.layout.yaml');

    expect(ContainerLayoutLoader::resolveComponentClassForSlot('btn_save'))->toBe(Button::class)
        ->and(ContainerLayoutLoader::resolveComponentClassForSlot('custom', ['component' => Input::class]))->toBe(Input::class)
        ->and(ContainerLayoutLoader::resolveComponentClassForSlot('custom', ['layout_slot' => 'main']))->toBe(Container::class)
        ->and(ContainerLayoutLoader::resolveComponentClassForSlot('unknown'))->toBeNull();

    expect(ContainerLayoutLoader::normalizeChildEntry(0, 'lbl_title', 1))->toBe([
        'slot' => 'lbl_title',
        'rule' => ['slot' => 'lbl_title'],
    ])->and(ContainerLayoutLoader::normalizeChildEntry(0, 123, 2))->toBe([
        'slot' => 2,
        'rule' => ['slot' => 2],
    ]);

    $ctx = (new LayoutApplicationContext(callingContext: FormDemo::class))
        ->withAutoTranslate(true)
        ->withBaseDir('/tmp')
        ->withVisitedPaths(['/tmp/a.layout.yaml'])
        ->withVisitedPath('/tmp/b.layout.yaml');

    expect($ctx->autoTranslate)->toBeTrue()
        ->and($ctx->baseDir)->toBe('/tmp')
        ->and($ctx->visitedPaths)->toBe(['/tmp/a.layout.yaml', '/tmp/b.layout.yaml']);
});
