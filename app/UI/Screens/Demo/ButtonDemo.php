<?php
namespace App\UI\Screens\Demo;

use App\UI\Screens\Demo\Events\Main;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class ButtonDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected const string KEY_PREFIX = 'screen.demo.container_demo.';

    /** @var array<class-string<Screen>> $options */
    protected array $options = [
        CheckboxDemo::class,
        FormDemo::class,
        TabsDemo::class,
        TableDemo::class,
        SelectDemo::class,
        TextareaDemo::class,
        SplitDemo::class,
        ModalDemo::class,
        CalendarDemo::class,
        CarouselDemo::class,
        InputDemo::class,
        Main::class,
    ];

    protected Container $buttons_container;

    protected Container $message_container;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->message_container = UI::container('message_container')
            ->backgroundColor('#f0f0f0')->borderRadius(Spacing::px(10))
            ->width(Size::px(1100))
            ->padding(Spacing::px(5));

        $container
            ->alignContent('center')->alignItems('center')
            ->title(t(self::KEY_PREFIX . 'title'))
            ->padding(Spacing::px(10))->maxWidth(Size::px(1200))
            ->centerHorizontal()->plain()
            ->gap(Spacing::px(20))
            ->add($this->buttonsContainer())
            ->add($this->message_container);
    }

    private function buttonsContainer(): Container
    {
        $this->buttons_container = UI::container('buttons_container')
            ->layout(LayoutType::HORIZONTAL)
            ->alignContent('center')
            ->alignItems('center')
            ->gap(Spacing::px(10))
            ->plain();

        foreach ($this->options as $index => $demoClass) {
            $button = UI::button("button_$index")
                ->label($demoClass::getMenuLabel())
                ->icon($demoClass::getMenuIcon() ?? '')
                ->status($index === 0)
                ->style($index === 0 ? 'primary' : 'secondary');
            $id = $button->getId();
            $button->action('demo_selected', [
                'id' => $id,
                'index' => $index,
            ]);
            $this->buttons_container->add($button);
        }
        return $this->buttons_container;
    }

    /**
     * Handle the action when a demo button is selected.
     *
     * @param  array<string, mixed> $params Parameters containing the button ID
     * and index of the selected demo
     */
    public function onDemoSelected(array $params): void
    {
        // TODO: Creo que los eventos deberían ser más específicos, por ejemplo:
        // onDemoSelected($id, $index) en lugar de pasar un array de parámetros.
        // Esto haría que el código sea más claro y fácil de mantener.

        /** @var string|null $id */
        $id = $params['id'] ?? null;
        if ($id === null) {
            return;
        }

        /** @var int|null $index */
        $index = $params['index'] ?? null;
        if ($index === null) {
            return;
        }

        /** @var class-string<Screen> | null $screenClass */
        $screenClass = $this->options[$index] ?? null;
        if ($screenClass === null) {
            return;
        }

        $this->updateButtonState($id);
        $this->message_container->embed($screenClass);
    }

    protected function postLoadUI(): void
    {
        $this->message_container->embed(
            $this->options[$this->selectedIndex()]
        );
    }

    private function updateButtonState(?string $id = null): void
    {
        foreach ($this->buttons_container->getChildren() as $button) {
            if ($button instanceof Button) {
                $button->status(false);
                $button->style('secondary');
            }
        }
        $button = $this->findRootComponentAs($id, Button::class);
        if ($button) {
            $button->status(true);
            $button->style('primary');
        }
    }

    private function selectedIndex(): int
    {
        foreach ($this->buttons_container->getChildren() as $index => $button) {
            if ($button instanceof Button && $button->getStatus()) {
                return $index;
            }
        }
        return -1;
    }
}
