<?php

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Widgets\Box;
use Idei\Usim\Widgets\Button;
use Idei\Usim\Widgets\Column;
use Idei\Usim\Widgets\Modal;
use Idei\Usim\Widgets\Row;
use Idei\Usim\Widgets\Text;
use Idei\Usim\Widgets\TextInput;
use Idei\Usim\Widgets\Widget;

it('mounts declarative widgets hierarchy correctly into container', function () {
    $parent = new Container('root_test');

    $widget = new Column(
        gap: 15,
        children: [
            new Text('Hola Mundo', style: 'h2', key: 'title'),
            new Row(
                children: [
                    new TextInput(name: 'username', label: 'Usuario', value: 'admin'),
                    new Button(label: 'Guardar', onPressed: 'save_data', key: 'btn_save'),
                ]
            ),
        ]
    );

    $element = $widget->mount($parent, 'TestContext');

    expect($element)->toBeInstanceOf(Container::class);
    $json = $parent->toJson();

    expect($json)->toBeArray();
    expect(count($json))->toBeGreaterThanOrEqual(4);
});

it('supports declarative screen with reactive state diffing', function () {
    $screen = new class extends Screen {
        public int $counter = 0;
        public bool $showModal = false;

        public function onIncrement(array $params = []): void
        {
            $this->counter++;
        }

        public function onOpenModal(array $params = []): void
        {
            $this->showModal = true;
        }

        public function build(): ?Widget
        {
            $children = [
                new Text("Contador: {$this->counter}", key: 'counter_label'),
                new Button(label: 'Sumar', onPressed: 'increment', key: 'increment_btn'),
            ];

            if ($this->showModal) {
                $children[] = new Modal(
                    title: 'Ventana Modal',
                    child: new Text('Contenido del modal'),
                    key: 'demo_modal'
                );
            }

            return new Box(
                child: new Column(children: $children)
            );
        }
    };

    expect($screen->isDeclarative())->toBeTrue();

    // 1. Initial render
    $screen->initializeEventContext();
    $screen->finalizeEventContext(reload: true);

    // 2. Action: increment counter
    $screen->onIncrement();
    $screen->finalizeEventContext(reload: false);

    // After increment, counter is 1
    expect($screen->counter)->toBe(1);

    // 3. Action: open modal
    $screen->onOpenModal();
    $screen->finalizeEventContext(reload: false);

    expect($screen->showModal)->toBeTrue();
});

it('isolates cache keys per tab using X-USIM-Tab-Id', function () {
    $keyWithoutTab = UIStateManager::getCacheKey('App\\UI\\Screens\\Home');

    request()->headers->set('X-USIM-Tab-Id', 'tab-abc-123');
    $keyWithTab = UIStateManager::getCacheKey('App\\UI\\Screens\\Home');

    expect($keyWithTab)->toContain('tab-abc-123');
    expect($keyWithTab)->not->toBe($keyWithoutTab);

    // Clean up header
    request()->headers->remove('X-USIM-Tab-Id');
});
