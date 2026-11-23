<?php

use App\UI\Screens\Demo\RichTextDemo;
use Idei\Usim\Components\RichText;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

test('UI::richText() returns a concrete RichText instance with fluent builder API', function () {
    $editor = UI::richText('article_body')
        ->label('Contenido')
        ->placeholder('Escribe aquí...')
        ->value('<p>Hola <strong>mundo</strong></p>')
        ->fullToolbar()
        ->allowImages(true)
        ->imageUploadUrl('/api/images/upload')
        ->maxLength(5000)
        ->charCounter(true)
        ->wordCounter(true)
        ->statusbar(true)
        ->lang('es')
        ->width(Size::full())
        ->height(Size::px(360))
        ->minHeight(Size::px(200))
        ->maxHeight(Size::px(600))
        ->required()
        ->helpText('Soporta imágenes editables')
        ->onChange('on_article_saved', ['section' => 'blog'])
        ->onInput('on_article_typing')
        ->debounce(300);

    expect($editor)->toBeInstanceOf(RichText::class)
        ->and($editor->getType())->toBe('rich_text')
        ->and($editor->getName())->toBe('article_body')
        ->and($editor->getValue())->toBe('<p>Hola <strong>mundo</strong></p>');

    $json = $editor->toJson();
    $config = $json[$editor->getId()];

    expect($config['type'])->toBe('rich_text')
        ->and($config['toolbar'])->toBe('full')
        ->and($config['allow_images'])->toBeTrue()
        ->and($config['image_upload_url'])->toBe('/api/images/upload')
        ->and($config['max_length'])->toBe(5000)
        ->and($config['char_counter'])->toBeTrue()
        ->and($config['word_counter'])->toBeTrue()
        ->and($config['lang'])->toBe('es')
        ->and($config['height'])->toBe('360px')
        ->and($config['min_height'])->toBe('200px')
        ->and($config['max_height'])->toBe('600px')
        ->and($config['on_change'])->toBe(['action' => 'on_article_saved', 'parameters' => ['section' => 'blog']])
        ->and($config['on_input'])->toBe(['action' => 'on_article_typing', 'parameters' => []])
        ->and($config['debounce'])->toBe(300);
});

test('RichText supports toolbar presets and custom button lists', function () {
    $basic = UI::richText('basic_rte')->basicToolbar()->allowImages(false);
    $basicConfig = $basic->toJson()[$basic->getId()];

    expect($basicConfig['toolbar'])->toBe('basic')
        ->and($basicConfig['allow_images'])->toBeFalse();

    $customList = [['bold', 'italic'], '|', ['image', 'link']];
    $custom = UI::richText('custom_rte')->toolbar($customList);
    $customConfig = $custom->toJson()[$custom->getId()];

    expect($customConfig['toolbar'])->toBe($customList);
});

test('Textarea no longer serializes markdown mode configuration', function () {
    $textarea = UI::textarea('plain_only')->value('Hola');
    $config = $textarea->toJson()[$textarea->getId()];

    expect(array_key_exists('mode', $config))->toBeFalse();
});

test('RichTextDemo handles onChange and form submission events with client value sync', function () {
    $ui = uiScenario($this, RichTextDemo::class, ['reset' => true]);
    $articleEditor = $ui->component('article_editor');
    $saveButton = $ui->component('btn_save_demo');

    $changeResponse = $this->postJson('/api/ui-event', [
        'component_id' => $articleEditor->id(),
        'event' => 'change',
        'action' => 'on_article_saved',
        'parameters' => [
            'value' => '<p>Texto <strong>enriquecido</strong></p>',
            'article_editor' => '<p>Texto <strong>enriquecido</strong></p>',
        ],
    ]);

    $changeResponse->assertOk();
    $changeResponse->assertJson([
        'toast' => [
            'message' => 'Artículo guardado (17 car.)',
        ],
    ]);

    $formResponse = $this->postJson('/api/ui-event', [
        'component_id' => $saveButton->id(),
        'event' => 'click',
        'action' => 'save_form',
        'parameters' => [
            'article_editor' => '<p>Artículo final</p>',
            'summary_editor' => '<p>Resumen</p>',
        ],
    ]);

    $formResponse->assertOk();
    $formResponse->assertJson([
        'toast' => [
            'message' => 'Formulario RichText guardado correctamente',
        ],
    ]);
});
