<?php

namespace App\UI\Layouts;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class MainLayout extends AbstractLayout
{
    /**
     * Build the layout structure wrapping the screen content.
     *
     * @param Container $root
     * @param Closure(Container): void $contentBuilder
     * @param class-string<Screen>|null $menuScreen
     */
    public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void
    {
        $root
            ->plain()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->width(Size::full())
            ->minHeight(Size::vh(100));

        // 1. Top menu slot container
        $this->mainMenuContainer = UI::container('main_menu_container')
            ->plain()
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $root->add($this->mainMenuContainer);

        $effectiveMenu = $menuScreen ?? config('usim.default_menu_screen', \App\UI\Screens\Menu::class);
        if ($effectiveMenu !== null && class_exists($effectiveMenu)) {
            Screen::embedInto($effectiveMenu, $this->mainMenuContainer);
        }

        // 2. Main content slot container
        $this->contentContainer = UI::container('content_container')
            ->plain()
            ->width(Size::full())
            ->flexGrow(1);

        $root->add($this->contentContainer);

        $contentBuilder($this->contentContainer);
    }
}
