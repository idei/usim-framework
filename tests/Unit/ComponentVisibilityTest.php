<?php

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;

it('serializes visible false in Container when configured with visible(false)', function () {
    $container = UI::container('edit_panel')
        ->layout(LayoutType::VERTICAL)
        ->visible(false);

    $data = $container->toJson();
    $config = $data[$container->getId()];

    expect($config)->toHaveKey('visible');
    expect($config['visible'])->toBeFalse();
    expect($container->isVisible())->toBeFalse();
});

it('omits visible property when Container is visible(true) by default', function () {
    $container = UI::container('edit_panel')
        ->layout(LayoutType::VERTICAL);

    $data = $container->toJson();
    $config = $data[$container->getId()];

    expect($config)->not->toHaveKey('visible');
    expect($container->isVisible())->toBeTrue();
});

it('serializes visible false in UIComponent (Button, Label, Input) when configured with visible(false)', function () {
    $button = UI::button('my_btn')->label('Test')->visible(false);
    $label = UI::label('my_lbl')->text('Test')->visible(false);
    $input = UI::input('my_input')->visible(false);

    $buttonConfig = $button->toJson()[$button->getId()];
    $labelConfig = $label->toJson()[$label->getId()];
    $inputConfig = $input->toJson()[$input->getId()];

    expect($buttonConfig)->toHaveKey('visible');
    expect($buttonConfig['visible'])->toBeFalse();

    expect($labelConfig)->toHaveKey('visible');
    expect($labelConfig['visible'])->toBeFalse();

    expect($inputConfig)->toHaveKey('visible');
    expect($inputConfig['visible'])->toBeFalse();

    expect($button->isVisible())->toBeFalse();
    expect($label->isVisible())->toBeFalse();
    expect($input->isVisible())->toBeFalse();
});

it('tracks dirtyKeys when setVisible is called on UIComponent', function () {
    $button = UI::button('my_btn');
    $button->clearDirtyKeys();
    expect($button->getDirtyKeys())->toBeEmpty();

    $button->visible(false);
    expect($button->getDirtyKeys())->toContain('visible');
});
