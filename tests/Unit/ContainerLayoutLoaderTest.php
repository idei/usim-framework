<?php

use App\UI\Screens\Demo\FormDemo;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;
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

it('supports clean slot-free YAML syntax with bare strings and key-value entries', function () {
    $container = UI::container('clean_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'gap' => 'm',
            'padding' => 'l',
            'default_span' => 2,
            'children' => [
                ['lbl_title' => ['justify' => 'center', 'style' => 'info']],
                'input_name',
                ['btn_submit' => ['span' => 1, 'justify' => 'start', 'style' => 'primary', 'action' => 'submit_form']],
                ['btn_reset' => ['span' => 1, 'justify' => 'end', 'style' => 'secondary']],
            ],
        ],
    ]);

    $lblTitle = UI::newLabel('lbl_title')->text('Title');
    $inputName = UI::input('input_name');
    $btnSubmit = UI::button('btn_submit')->label('Submit');
    $btnReset = UI::button('btn_reset')->label('Reset');

    $container->addMany([$btnReset, $lblTitle, $btnSubmit, $inputName]);

    $json = $container->toJson();

    // Check semantic spacing
    expect($json[$container->getId()]['gap'])->toBe('16px')
        ->and($json[$container->getId()]['padding'])->toBe('24px');

    // Check order and placement
    expect($json[$lblTitle->getId()]['_order'])->toBe(1)
        ->and($json[$lblTitle->getId()]['grid_column'])->toBe('span 2')
        ->and($json[$lblTitle->getId()]['justify_self'])->toBe('center')
        ->and($json[$lblTitle->getId()]['style'])->toBe('info');

    expect($json[$inputName->getId()]['_order'])->toBe(2)
        ->and($json[$inputName->getId()]['grid_column'])->toBe('span 2');

    expect($json[$btnSubmit->getId()]['_order'])->toBe(3)
        ->and($json[$btnSubmit->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnSubmit->getId()]['justify_self'])->toBe('start')
        ->and($json[$btnSubmit->getId()]['style'])->toBe('primary')
        ->and($json[$btnSubmit->getId()]['action'])->toBe('submit_form');

    expect($json[$btnReset->getId()]['_order'])->toBe(4)
        ->and($json[$btnReset->getId()]['grid_column'])->toBe('span 1')
        ->and($json[$btnReset->getId()]['justify_self'])->toBe('end')
        ->and($json[$btnReset->getId()]['style'])->toBe('secondary');
});

it('supports children as an associative dictionary mapping slot names to rules', function () {
    $container = UI::container('map_grid')->layoutDefinition([
        'grid' => [
            'cols' => ['1fr', '1fr'],
            'children' => [
                'header' => ['justify' => 'center'],
                'body' => null,
            ],
        ],
    ]);

    $body = UI::container('body');
    $header = UI::container('header');

    $container->addMany([$body, $header]);

    $json = $container->toJson();
    expect($json[$header->getId()]['_order'])->toBe(1)
        ->and($json[$header->getId()]['justify_self'])->toBe('center');

    expect($json[$body->getId()]['_order'])->toBe(2);
});

class TestNoStartingUIOrLayoutScreen extends Screen {}

class TestDeclarativeStaticLayoutScreen extends Screen
{
    protected static ?string $layoutFile = 'app/UI/Screens/Demo/form-demo.layout.yaml';

    protected static bool $autoTranslate = true;

    protected NewLabel $lbl_instruction;

    protected Input $input_name;

    protected Input $input_email;

    protected Textarea $input_message;

    protected Button $btn_submit;

    protected Button $btn_reset;

    protected NewLabel $lbl_result;
}

it('applies card and center_horizontal container box properties from layout definition', function () {
    $container = UI::container('box_props')->layoutDefinition([
        'card' => true,
        'center_horizontal' => true,
        'max_width' => 640,
        'grid' => [
            'cols' => 1,
            'children' => ['lbl_only'],
        ],
    ]);

    $container->add(UI::newLabel('lbl_only')->text('Hello'));

    $json = $container->toJson();
    $data = $json[$container->getId()];

    expect($data['appearance'])->toBe('card')
        ->and($data['margin_left'])->toBe('auto')
        ->and($data['margin_right'])->toBe('auto')
        ->and($data['max_width'])->toBe('640px');
});

it('resolves explicit relative and global i18n keys and preserves literals in layout rules', function () {
    $translator = app('translator');
    $translator->addLines([
        'screens.demo.form_demo.custom_literal_check' => 'Resolved Relative Text',
        'global_badge.reset' => 'Global Reset Label',
    ], app()->getLocale());

    $container = UI::container('i18n_rules')->layoutDefinition([
        'grid' => [
            'cols' => 1,
            'children' => [
                ['lbl_rel' => ['text' => '.custom_literal_check']],
                ['btn_global' => ['label' => 't:global_badge.reset']],
                ['input_lit' => ['placeholder' => '...']],
            ],
        ],
    ], callingContext: FormDemo::class);

    $lbl = UI::newLabel('lbl_rel');
    $btn = UI::button('btn_global');
    $input = UI::input('input_lit');

    $container->addMany([$lbl, $btn, $input]);
    $json = $container->toJson();

    expect($json[$lbl->getId()]['text'])->toBe('Resolved Relative Text')
        ->and($json[$btn->getId()]['label'])->toBe('Global Reset Label')
        ->and($json[$input->getId()]['placeholder'])->toBe('...');
});

it('respects PHP > YAML > i18n auto-hydration priority and translates untouched defaults like Select placeholder', function () {
    $locale = app()->getLocale();
    app('translator')->addLines([
        'screens.demo.form_demo.country.label' => 'Auto Country',
        'screens.demo.form_demo.country.placeholder' => 'Select Country Auto',
    ], $locale);

    $container = UI::container('priority_grid')->layoutDefinition([
        'i18n' => 'auto',
        'grid' => [
            'cols' => 1,
            'children' => [
                ['lbl_instruction' => ['text' => 'Explicit YAML Wins Auto']],
                ['input_name'],
                ['sel_country'],
                ['btn_submit'],
            ],
        ],
    ], callingContext: FormDemo::class);

    $lbl = UI::newLabel('lbl_instruction');
    $inputName = UI::input('input_name')->placeholder('Explicit PHP Placeholder');
    $select = UI::select('sel_country');
    $btn = UI::button('btn_submit');

    $container->addMany([$lbl, $inputName, $select, $btn]);
    $json = $container->toJson();

    // YAML > Auto
    expect($json[$lbl->getId()]['text'])->toBe('Explicit YAML Wins Auto');

    // PHP > Auto (placeholder set in PHP kept, label auto-hydrated)
    expect($json[$inputName->getId()]['placeholder'])->toBe('Explicit PHP Placeholder')
        ->and($json[$inputName->getId()]['label'])->toBe(t('screens.demo.form_demo.name.label'));

    // Non-empty default Select placeholder still gets hydrated when untouched
    expect($json[$select->getId()]['label'])->toBe('Auto Country')
        ->and($json[$select->getId()]['placeholder'])->toBe('Select Country Auto');

    // Button group alias ('actions.submit') resolves cleanly
    expect($json[$btn->getId()]['label'])->toBe(t('screens.demo.form_demo.actions.submit'));
});

it('allows omitting startingUI when Screen has a default or declared .layout.yaml and FormDemo uses auto layout', function () {
    expect(method_exists(FormDemo::class, 'startingUI'))->toBeFalse();

    $harness = ScreenTestHarness::for(FormDemo::class);
    $harness->render();

    $harness->assertHasComponent('form_wrapper')
        ->assertHasComponent('input_name')
        ->assertHasComponent('btn_submit');

    expect($harness->getComponent('input_name')->get('label'))->toBe(t('screens.demo.form_demo.name.label'))
        ->and($harness->getComponent('input_name')->get('help_text'))->toBe(t('screens.demo.form_demo.validation.name_required'))
        ->and($harness->getComponent('btn_submit')->get('label'))->toBe(t('screens.demo.form_demo.actions.submit'));

    // Also verifies static $layoutFile fallback works without startingUI()
    $staticHarness = ScreenTestHarness::for(TestDeclarativeStaticLayoutScreen::class);
    $staticHarness->render();
    $staticHarness->assertHasComponent('form_wrapper')
        ->assertHasComponent('input_name');

    // Screen without startingUI, buildBaseUI, or any .layout.yaml still throws LogicException
    expect(fn () => ScreenTestHarness::for(TestNoStartingUIOrLayoutScreen::class)->render())
        ->toThrow(LogicException::class);
});
