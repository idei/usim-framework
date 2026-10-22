<?php

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;
use Idei\Usim\UI;

// Screen fixture reproducing the reported ordering scenario
class TextareaOrderingDemoScreen extends Screen
{
    protected Input $post_title;
    protected Textarea $post_content;
    protected Uploader $post_image;
    protected Button $btn_save;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $card = UI::container('publish_form_card');

        $this->post_title = UI::input('post_title')
            ->label('Título del Post');

        // Unnamed textarea triggers reconcileComponentProperties() reindexing
        $this->post_content = UI::textarea()
            ->label('Contenido')
            ->placeholder('Escribe algo...')
            ->onChange('on_plain_saved');

        $this->post_image = UI::uploader('post_image')
            ->label('Imagen de Portada');

        $this->btn_save = UI::button('btn_save')
            ->label('Publicar');

        $card->add($this->post_title);
        $card->add($this->post_content);
        $card->add($this->post_image);
        $card->add($this->btn_save);

        $container->add($card);
    }
}

test('UI::textarea() returns a concrete Textarea instance with fluent builder API', function () {
    $textarea = UI::textarea('test_notes')
        ->label('Notas')
        ->placeholder('Placeholder...')
        ->value('Initial text')
        ->plainText()
        ->maxLength(500)
        ->helpText('Ayuda')
        ->onChange('on_saved', ['extra' => 123])
        ->onInput('on_typing')
        ->debounce(300);

    expect($textarea)->toBeInstanceOf(Textarea::class)
        ->and($textarea->getValue())->toBe('Initial text')
        ->and($textarea->getName())->toBe('test_notes');
});

test('Container::reindexChild() preserves original insertion order of children', function () {
    $container = UI::container('test_card');
    $title = UI::input('title');
    $content = UI::textarea(); // Unnamed, has auto ID
    $image = UI::uploader('image');
    $btn = UI::button('btn');

    $container->add($title);
    $container->add($content);
    $container->add($image);
    $container->add($btn);

    $oldId = $content->getId();
    $newId = 999888;

    $content->setId($newId);
    $container->reindexChild($oldId, $newId);

    $children = $container->getChildren();

    // Child in index 1 must still be the content component with newId
    expect($children[0]->getId())->toBe($title->getId())
        ->and($children[1]->getId())->toBe($newId)
        ->and($children[2]->getId())->toBe($image->getId())
        ->and($children[3]->getId())->toBe($btn->getId());

    $json = $container->toJson();
    expect($json[(string) $title->getId()]['_order'])->toBe(1)
        ->and($json[(string) $newId]['_order'])->toBe(2)
        ->and($json[(string) $image->getId()]['_order'])->toBe(3)
        ->and($json[(string) $btn->getId()]['_order'])->toBe(4);
});

test('auto-naming unnamed textarea preserves position between input and uploader in rendered screen', function () {
    $harness = ScreenTestHarness::for(TextareaOrderingDemoScreen::class);
    $harness->render();

    $tree = $harness->getContainer()->toJson();

    $titleComp = collect($tree)->firstWhere('name', 'post_title');
    $contentComp = collect($tree)->firstWhere('name', 'post_content');
    $imageComp = collect($tree)->firstWhere('name', 'post_image');
    $btnComp = collect($tree)->firstWhere('name', 'btn_save');

    expect($titleComp)->not->toBeNull()
        ->and($contentComp)->not->toBeNull()
        ->and($imageComp)->not->toBeNull()
        ->and($btnComp)->not->toBeNull();

    // Verify orders are strictly sequential: Title (1) -> Textarea (2) -> Uploader (3) -> Button (4)
    expect($titleComp['_order'])->toBe(1)
        ->and($contentComp['_order'])->toBe(2)
        ->and($imageComp['_order'])->toBe(3)
        ->and($btnComp['_order'])->toBe(4);
});

test('Screen::actionToMethodName() normalizes snake_case, on_ prefix, camelCase and onPascalCase', function () {
    expect(Screen::actionToMethodName('plain_saved'))->toBe('onPlainSaved')
        ->and(Screen::actionToMethodName('on_plain_saved'))->toBe('onPlainSaved')
        ->and(Screen::actionToMethodName('on_md_saved'))->toBe('onMdSaved')
        ->and(Screen::actionToMethodName('onPlainSaved'))->toBe('onPlainSaved')
        ->and(Screen::actionToMethodName('submit_form'))->toBe('onSubmitForm')
        ->and(Screen::actionToMethodName('on_submit_form'))->toBe('onSubmitForm')
        ->and(Screen::actionToMethodName('submitForm'))->toBe('onSubmitForm')
        ->and(Screen::actionToMethodName('online_status_changed'))->toBe('onOnlineStatusChanged');
});

test('UIEventController handles action with on_ prefix without 404 (e.g. on_plain_saved in TextareaDemo)', function () {
    $ui = uiScenario($this, \App\UI\Screens\Demo\TextareaDemo::class, ['reset' => true]);
    $plainTextarea = $ui->component('plain_textarea');
    $componentId = $plainTextarea->id();

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'change',
        'action' => 'on_plain_saved',
        'parameters' => [
            'value' => 'Notas de prueba',
            'plain_textarea' => 'Notas de prueba',
        ],
    ]);

    $response->assertOk();
    $response->assertJson([
        'toast' => [
            'message' => 'Texto guardado (15 car.)',
        ],
    ]);

    $mdTextarea = $ui->component('md_textarea');
    $mdComponentId = $mdTextarea->id();

    $mdResponse = $this->postJson('/api/ui-event', [
        'component_id' => $mdComponentId,
        'event' => 'change',
        'action' => 'on_md_saved',
        'parameters' => [
            'value' => '# Título Markdown',
            'md_textarea' => '# Título Markdown',
        ],
    ]);

    $mdResponse->assertOk();
    $mdResponse->assertJson([
        'toast' => [
            'message' => 'Markdown guardado (17 car.)',
        ],
    ]);
});

