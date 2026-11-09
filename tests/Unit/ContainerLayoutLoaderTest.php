<?php

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;

class TestSlotEmbedLeftScreen extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_left')->text('Left Content'));
    }
}

class TestSlotEmbedRightScreen extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_right')->text('Right Content'));
    }
}

class TestSlotEmbedReplacementScreen extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_replacement')->text('Replacement Content'));
    }
}

it('applies declarative B2 grid layout definition to container and children', function () {
    $container = UI::container('test_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'gap' => 24,
            'padding' => 24,
            'default_span' => 2,
            'children' => [
                ['slot' => 'lbl_title', 'justify' => 'center'],
                ['slot' => 'input_a'],
                ['slot' => 'btn_ok', 'span' => 1, 'justify' => 'end'],
                ['slot' => 'btn_cancel', 'span' => 1, 'justify' => 'start'],
            ],
        ],
    ]);

    // Add children out of order to verify YAML children order takes precedence
    $btnCancel = UI::button('btn_cancel')->label('Cancel');
    $btnOk = UI::button('btn_ok')->label('OK');
    $inputA = UI::input('input_a')->label('A');
    $lblTitle = UI::newLabel('lbl_title')->text('Title');

    $container->addMany([$btnCancel, $inputA, $lblTitle, $btnOk]);

    $json = $container->toJson();

    $containerData = $json[$container->getId()];
    expect($containerData['layout'])->toBe('grid')
        ->and($containerData['grid_template_columns'])->toBe('1fr 1fr')
        ->and($containerData['gap'])->toBe('24px')
        ->and($containerData['padding'])->toBe('24px');

    expect($json[$lblTitle->getId()]['_order'])->toBe(1)
        ->and($json[$lblTitle->getId()]['grid_column'])->toBe('span 2')
        ->and($json[$lblTitle->getId()]['justify_self'])->toBe('center');

    expect($json[$inputA->getId()]['_order'])->toBe(2)
        ->and($json[$inputA->getId()]['grid_column'])->toBe('span 2');

    expect($json[$btnOk->getId()]['_order'])->toBe(3)
        ->and($json[$btnOk->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnOk->getId()]['justify_self'])->toBe('end');

    expect($json[$btnCancel->getId()]['_order'])->toBe(4)
        ->and($json[$btnCancel->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnCancel->getId()]['justify_self'])->toBe('start');
});

it('auto-mounts screen component properties referenced in layout children slots', function () {
    $screen = new class extends Screen
    {
        protected NewLabel $lbl_header;

        protected Input $input_field;

        protected Button $btn_save;

        protected Button $btn_reset;

        protected function buildBaseUI(Container $container, ...$params): void
        {
            $card = UI::container('card')->layoutDefinition([
                'grid' => [
                    'cols' => ['1fr', '1fr'],
                    'default_span' => 2,
                    'children' => [
                        ['slot' => 'lbl_header', 'justify' => 'center'],
                        ['slot' => 'input_field'],
                        ['slot' => 'btn_save', 'span' => 1],
                        ['slot' => 'btn_reset', 'span' => 1],
                    ],
                ],
            ]);

            // Configure properties without manually calling $card->add(...)
            $this->lbl_header->text('Header');
            $this->input_field->label('Field');
            $this->btn_save->label('Save');
            $this->btn_reset->label('Reset');

            $container->add($card);
        }
    };

    $root = UI::container('root');
    $screen->callBuildBaseUI($root);

    $card = $root->findByName('card');
    expect($card)->toBeInstanceOf(Container::class);

    /** @var Container $card */
    $childNames = array_map(fn ($c) => $c->getName(), $card->getChildren());
    expect($childNames)->toBe(['lbl_header', 'input_field', 'btn_save', 'btn_reset']);
});

it('targets specific layout slots via slot(), add(..., slot: ...), and addToSlot(...)', function () {
    $container = UI::container('slot_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'default_span' => 2,
            'children' => [
                ['slot' => 'header_slot', 'justify' => 'center'],
                ['slot' => 'main_slot'],
                ['slot' => 'action_primary', 'span' => 1, 'justify' => 'end'],
                ['slot' => 'action_secondary', 'span' => 1, 'justify' => 'start'],
            ],
        ],
    ]);

    // 1. Fluent ->slot(...) on component with a different component name
    $btnLimpiar = UI::button('btn_limpiar')->label('Limpiar')->slot('action_secondary');

    // 2. Named parameter slot: on $container->add(...)
    $btnGuardar = UI::button('btn_guardar')->label('Guardar');
    $container->add($btnGuardar, slot: 'action_primary');

    // 3. $container->addToSlot($slot, $element) and $container->addToSlot($element, $slot)
    $inputMain = UI::input('campo_principal')->label('Main');
    $lblHeader = UI::newLabel('titulo_cabecera')->text('Cabecera');

    $container->add($btnLimpiar);
    $container->addToSlot('main_slot', $inputMain);
    $container->addToSlot($lblHeader, 'header_slot');

    $json = $container->toJson();

    expect($json[$lblHeader->getId()]['_order'])->toBe(1)
        ->and($json[$lblHeader->getId()]['name'])->toBe('titulo_cabecera')
        ->and($json[$lblHeader->getId()]['justify_self'])->toBe('center');

    expect($json[$inputMain->getId()]['_order'])->toBe(2)
        ->and($json[$inputMain->getId()]['grid_column'])->toBe('span 2');

    expect($json[$btnGuardar->getId()]['_order'])->toBe(3)
        ->and($json[$btnGuardar->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnGuardar->getId()]['justify_self'])->toBe('end');

    expect($json[$btnLimpiar->getId()]['_order'])->toBe(4)
        ->and($json[$btnLimpiar->getId()]['name'])->toBe('btn_limpiar')
        ->and($json[$btnLimpiar->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnLimpiar->getId()]['justify_self'])->toBe('start');
});

it('supports targeting a layout slot by 1-based numeric position', function () {
    $container = UI::container('numeric_slot_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'default_span' => 2,
            'children' => [
                ['slot' => 'first_slot'],
                ['slot' => 'second_slot', 'span' => 1, 'justify' => 'end'],
                ['slot' => 'third_slot', 'span' => 1, 'justify' => 'start'],
            ],
        ],
    ]);

    $first = UI::newLabel('first_slot')->text('First');
    $third = UI::button('third_slot')->label('Third');
    $customSecond = UI::button('custom_second_btn')->label('Second')->slot(2);

    // Add customSecond last, but it targets slot #2
    $container->addMany([$first, $third, $customSecond]);

    $json = $container->toJson();

    expect($json[$first->getId()]['_order'])->toBe(1)
        ->and($json[$customSecond->getId()]['_order'])->toBe(2)
        ->and($json[$customSecond->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$customSecond->getId()]['justify_self'])->toBe('end')
        ->and($json[$third->getId()]['_order'])->toBe(3);
});

it('embeds multiple screens into specific layout slots and replaces only the target slot on re-embed', function () {
    $dashboard = UI::container('dashboard_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'gap' => 16,
            'default_span' => 2,
            'children' => [
                ['slot' => 'header_slot', 'justify' => 'center'],
                ['slot' => 'left_panel', 'span' => 1, 'align' => 'start'],
                ['slot' => 'right_panel', 'span' => 1, 'align' => 'stretch'],
            ],
        ],
    ]);

    $header = UI::newLabel('header_slot')->text('Dashboard Header');
    $dashboard->add($header);

    // Embed two different screens into left_panel and right_panel without clearing header_slot
    $dashboard->embedToSlot('right_panel', TestSlotEmbedRightScreen::class);
    $dashboard->embed(TestSlotEmbedLeftScreen::class, slot: 'left_panel');

    $children = array_values($dashboard->getChildren());
    expect($children)->toHaveCount(3)
        ->and($children[0]->getName())->toBe('header_slot')
        ->and($children[1]->getName())->toBe('testslotembedleftscreen')
        ->and($children[2]->getName())->toBe('testslotembedrightscreen');

    $json = $dashboard->toJson();
    $leftRootId = $children[1]->getId();
    $rightRootId = $children[2]->getId();

    expect($json[$header->getId()]['_order'])->toBe(1)
        ->and($json[$leftRootId]['_order'])->toBe(2)
        ->and($json[$leftRootId]['slot'])->toBe('left_panel')
        ->and($json[$leftRootId]['grid_column'])->toBe('span 1')
        ->and($json[$leftRootId]['align_self'])->toBe('start')
        ->and($json[$rightRootId]['_order'])->toBe(3)
        ->and($json[$rightRootId]['slot'])->toBe('right_panel')
        ->and($json[$rightRootId]['grid_column'])->toBe('span 1')
        ->and($json[$rightRootId]['align_self'])->toBe('stretch');

    // Now replace only 'left_panel' with TestSlotEmbedReplacementScreen
    $collector = app(UIChangesCollector::class);
    $collector->reset();

    $dashboard->embed(TestSlotEmbedReplacementScreen::class, slot: 'left_panel');

    $updatedChildren = array_values($dashboard->getChildren());
    expect($updatedChildren)->toHaveCount(3)
        ->and($updatedChildren[0]->getId())->toBe($header->getId())
        ->and($updatedChildren[1]->getName())->toBe('testslotembedreplacementscreen')
        ->and($updatedChildren[2]->getId())->toBe($rightRootId);

    $changes = $collector->all();
    // Previous left screen root container should be marked removed (parent => null)
    expect($changes[$leftRootId]['parent'])->toBeNull()
        ->and($changes[$updatedChildren[1]->getId()]['_order'])->toBe(2)
        ->and($changes[$updatedChildren[1]->getId()]['grid_column'])->toBe('span 1');
});
