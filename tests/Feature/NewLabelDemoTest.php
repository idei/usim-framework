<?php

use App\UI\Screens\Demo\NewLabelDemo;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\Animation;
use Idei\Usim\Enums\FontWeight;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\TextTransform;
use Idei\Usim\Enums\Variant;
use Idei\Usim\UI;

it('builds NewLabel component with typed enums and returns correct json config', function () {
    $label = UI::newLabel('test_label')
        ->text('Hello World')
        ->style(Style::PRIMARY)
        ->variant(Variant::BADGE)
        ->size(TextSize::LARGE)
        ->fontWeight(FontWeight::BOLD)
        ->textTransform(TextTransform::UPPERCASE)
        ->textAlign(Align::CENTER)
        ->icon('⭐', Align::RIGHT)
        ->shadow(true)
        ->glow(true)
        ->animation(Animation::PULSE)
        ->block();

    $json = $label->toJson();
    $id = $label->getId();

    expect($json)->toHaveKey($id);
    $config = $json[$id];

    expect($config['type'])->toBe('newlabel')
        ->and($config['name'])->toBe('test_label')
        ->and($config['text'])->toBe('Hello World')
        ->and($config['style'])->toBe('primary')
        ->and($config['variant'])->toBe('badge')
        ->and($config['size'])->toBe('large')
        ->and($config['font_weight'])->toBe('bold')
        ->and($config['text_transform'])->toBe('uppercase')
        ->and($config['text_align'])->toBe('center')
        ->and($config['icon'])->toBe('⭐')
        ->and($config['icon_position'])->toBe('right')
        ->and($config['shadow'])->toBeTrue()
        ->and($config['glow'])->toBeTrue()
        ->and($config['animation'])->toBe('pulse')
        ->and($config['inline'])->toBeFalse();
});

it('supports fluent convenience helpers on NewLabel', function () {
    $label = UI::newLabel('badge_demo')
        ->text('Success Tag')
        ->success()
        ->pill()
        ->small()
        ->semibold()
        ->center();

    $config = $label->toJson()[$label->getId()];

    expect($config['style'])->toBe('success')
        ->and($config['variant'])->toBe('pill')
        ->and($config['size'])->toBe('small')
        ->and($config['font_weight'])->toBe('semibold')
        ->and($config['text_align'])->toBe('center');
});

it('loads NewLabelDemo screen with all sections rendered', function () {
    $ui = uiScenario($this, NewLabelDemo::class, ['reset' => true]);

    $title = $ui->component('demo_title');
    expect($title)->not->toBeNull();
    expect($title->data()['type'])->toBe('newlabel');
    expect($title->data()['style'])->toBe('primary');

    $interactive = $ui->component('lbl_interactive');
    expect($interactive)->not->toBeNull();
    expect($interactive->data()['type'])->toBe('newlabel');
    expect($interactive->data()['text'])->toBe('Estado inicial: 0 clics');

    $ui->assertNoIssues();
});

it('reactively updates NewLabel on NewLabelDemo screen events', function () {
    $ui = uiScenario($this, NewLabelDemo::class, ['reset' => true]);

    // Click Success
    $response = $ui->click('btn_success');
    $response->assertOk();

    $interactive = $ui->component('lbl_interactive');
    expect($interactive->data()['style'])->toBe('success');
    expect($interactive->data()['variant'])->toBe('chip');
    expect($interactive->data()['text'])->toBe('Actualizado a success (1 clics)');
    expect($interactive->data()['icon'])->toBe('✅');

    // Click Warning
    $response = $ui->click('btn_warning');
    $response->assertOk();

    $interactive = $ui->component('lbl_interactive');
    expect($interactive->data()['style'])->toBe('warning');
    expect($interactive->data()['variant'])->toBe('tag');
    expect($interactive->data()['text'])->toBe('Actualizado a warning (2 clics)');
    expect($interactive->data()['icon'])->toBe('⚠️');

    // Click Danger
    $response = $ui->click('btn_danger');
    $response->assertOk();

    $interactive = $ui->component('lbl_interactive');
    expect($interactive->data()['style'])->toBe('danger');
    expect($interactive->data()['variant'])->toBe('badge');
    expect($interactive->data()['text'])->toBe('Actualizado a danger (3 clics)');
    expect($interactive->data()['icon'])->toBe('🔥');

    // Click Reset
    $response = $ui->click('btn_reset');
    $response->assertOk();

    $interactive = $ui->component('lbl_interactive');
    expect($interactive->data()['style'])->toBe('primary');
    expect($interactive->data()['variant'])->toBe('pill');
    expect($interactive->data()['text'])->toBe('Estado inicial: 0 clics');

    $ui->assertNoIssues();
});

