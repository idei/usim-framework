<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class SplitDemo extends Screen
{
    protected Split $split_workspace;
    protected Select $sel_split_orientation;
    protected Select $sel_collapse_target;
    protected Input $inp_split_size;
    protected Input $inp_splitter_size;
    protected Checkbox $chk_split_draggable;
    protected Checkbox $chk_split_collapsible;

    protected string $store_split_orientation = 'horizontal';
    protected string $store_split_size = '45%';
    protected string $store_splitter_size = '8px';
    protected string $store_collapse_target = 'first';
    protected string $store_collapsed_panel = 'none';
    protected bool $store_split_draggable = true;
    protected bool $store_split_collapsible = true;

    public static function getMenuLabel(): string
    {
        return 'Split Demo';
    }

    public static function getMenuIcon(): ?string
    {
        return '↔️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title('Split Container Demo')
            ->maxWidth('1100px')
            ->centerHorizontal()
            ->padding('24px')
            ->gap('14px')
            ->plain();

        $container->add(
            UI::label('split_demo_intro')
                ->text('Configura orientacion, ancho inicial y comportamiento de colapso. La barra del split es draggable.')
                ->style('info')
                ->width('100%')
        );

        $controls = UI::container('split_controls')
            ->layout(LayoutType::VERTICAL)
            ->gap('10px')
            ->padding('14px')
            ->width('100%');

        $controls->add(
            UI::select('sel_split_orientation')
                ->label('Orientacion')
                ->options([
                    ['value' => 'horizontal', 'label' => 'Horizontal'],
                    ['value' => 'vertical', 'label' => 'Vertical'],
                ])
                ->value($this->store_split_orientation)
                ->onChange('split_orientation_change')
                ->width('280px')
        );

        $controls->add(
            UI::input('inp_split_size')
                ->label('Ancho/alto primer panel (ej: 35% o 360px)')
                ->value($this->store_split_size)
                ->placeholder('45%')
                ->width('280px')
        );

        $controls->add(
            UI::input('inp_splitter_size')
                ->label('Ancho de barra split (ej: 8px)')
                ->value($this->store_splitter_size)
                ->placeholder('8px')
                ->width('280px')
        );

        $controls->add(
            UI::checkbox('chk_split_draggable')
                ->label('Draggable')
                ->checked($this->store_split_draggable)
                ->onChange('split_draggable_change')
        );

        $controls->add(
            UI::checkbox('chk_split_collapsible')
                ->label('Collapsible')
                ->checked($this->store_split_collapsible)
                ->onChange('split_collapsible_change')
        );

        $controls->add(
            UI::select('sel_collapse_target')
                ->label('Panel colapsable')
                ->options([
                    ['value' => 'first', 'label' => 'Primer panel'],
                    ['value' => 'second', 'label' => 'Segundo panel'],
                ])
                ->value($this->store_collapse_target)
                ->onChange('split_collapse_target_change')
                ->width('280px')
        );

        $actions = UI::container('split_actions')
            ->layout(LayoutType::HORIZONTAL)
            ->gap('8px')
            ->plain();

        $actions->add(
            UI::button('btn_apply_split_size')
                ->label('Aplicar medidas')
                ->style('primary')
                ->action('apply_split_size')
        );

        $actions->add(
            UI::button('btn_toggle_split_collapse')
                ->label('Toggle colapso')
                ->style('warning')
                ->action('toggle_split_collapse')
        );

        $actions->add(
            UI::button('btn_reset_split_demo')
                ->label('Reset demo')
                ->style('secondary')
                ->action('reset_split_demo')
        );

        $controls->add($actions);
        $container->add($controls);

        $this->split_workspace = UI::split('split_workspace')
            ->orientation($this->store_split_orientation)
            ->splitSize($this->store_split_size)
            ->splitterSize($this->store_splitter_size)
            ->draggable($this->store_split_draggable)
            ->collapsible($this->store_split_collapsible)
            ->collapseTarget($this->store_collapse_target)
            ->minFirstSize('160px')
            ->minSecondSize('160px')
            ->height('420px')
            ->width('100%')
            ->card();

        if ($this->store_collapsed_panel === 'first') {
            $this->split_workspace->collapseFirst();
        } elseif ($this->store_collapsed_panel === 'second') {
            $this->split_workspace->collapseSecond();
        } else {
            $this->split_workspace->expand();
        }

        $leftPanel = UI::container('split_left_panel')
            ->plain()
            ->padding('12px')
            ->gap('8px');

        $leftPanel->add(UI::label('split_left_title')->text('Panel A')->style('primary'));
        $leftPanel->add(UI::label('split_left_copy')->text('Este panel representa contenido principal.'));
        $leftPanel->add(
            UI::button('btn_collapse_first')
                ->label('Colapsar panel A')
                ->style('danger')
                ->action('collapse_first_panel')
        );

        $rightPanel = UI::container('split_right_panel')
            ->plain()
            ->padding('12px')
            ->gap('8px');

        $rightPanel->add(UI::label('split_right_title')->text('Panel B')->style('success'));
        $rightPanel->add(UI::label('split_right_copy')->text('Usa la barra central para redimensionar en vivo.'));
        $rightPanel->add(
            UI::button('btn_collapse_second')
                ->label('Colapsar panel B')
                ->style('danger')
                ->action('collapse_second_panel')
        );

        $this->split_workspace->addFirst($leftPanel);
        $this->split_workspace->addSecond($rightPanel);

        $container->add($this->split_workspace);
    }

    public function onSplitOrientationChange(array $params): void
    {
        $value = $params['value'] ?? 'horizontal';
        $this->store_split_orientation = $value === 'vertical' ? 'vertical' : 'horizontal';
        $this->split_workspace->orientation($this->store_split_orientation);
    }

    public function onSplitDraggableChange(array $params): void
    {
        $this->store_split_draggable = (bool) ($params['checked'] ?? false);
        $this->split_workspace->draggable($this->store_split_draggable);
    }

    public function onSplitCollapsibleChange(array $params): void
    {
        $this->store_split_collapsible = (bool) ($params['checked'] ?? false);
        $this->split_workspace->collapsible($this->store_split_collapsible);
    }

    public function onSplitCollapseTargetChange(array $params): void
    {
        $value = $params['value'] ?? 'first';
        $this->store_collapse_target = $value === 'second' ? 'second' : 'first';
        $this->split_workspace->collapseTarget($this->store_collapse_target);
    }

    public function onApplySplitSize(array $params): void
    {
        $splitSize = trim((string) ($params['inp_split_size'] ?? $this->store_split_size));
        $splitterSize = trim((string) ($params['inp_splitter_size'] ?? $this->store_splitter_size));

        if ($splitSize !== '') {
            $this->store_split_size = $splitSize;
            $this->split_workspace->splitSize($this->store_split_size);
            $this->inp_split_size->value($this->store_split_size);
        }

        if ($splitterSize !== '') {
            $this->store_splitter_size = $splitterSize;
            $this->split_workspace->splitterSize($this->store_splitter_size);
            $this->inp_splitter_size->value($this->store_splitter_size);
        }
    }

    public function onToggleSplitCollapse(array $params): void
    {
        if ($this->store_collapsed_panel === 'none') {
            $this->store_collapsed_panel = $this->store_collapse_target;
        } else {
            $this->store_collapsed_panel = 'none';
        }

        $this->applyCollapsedState();
    }

    public function onCollapseFirstPanel(array $params): void
    {
        $this->store_collapsed_panel = 'first';
        $this->applyCollapsedState();
    }

    public function onCollapseSecondPanel(array $params): void
    {
        $this->store_collapsed_panel = 'second';
        $this->applyCollapsedState();
    }

    public function onResetSplitDemo(array $params): void
    {
        $this->store_split_orientation = 'horizontal';
        $this->store_split_size = '45%';
        $this->store_splitter_size = '8px';
        $this->store_collapse_target = 'first';
        $this->store_collapsed_panel = 'none';
        $this->store_split_draggable = true;
        $this->store_split_collapsible = true;

        $this->sel_split_orientation->value($this->store_split_orientation);
        $this->sel_collapse_target->value($this->store_collapse_target);
        $this->inp_split_size->value($this->store_split_size);
        $this->inp_splitter_size->value($this->store_splitter_size);
        $this->chk_split_draggable->checked($this->store_split_draggable);
        $this->chk_split_collapsible->checked($this->store_split_collapsible);

        $this->split_workspace
            ->orientation($this->store_split_orientation)
            ->splitSize($this->store_split_size)
            ->splitterSize($this->store_splitter_size)
            ->draggable($this->store_split_draggable)
            ->collapsible($this->store_split_collapsible)
            ->collapseTarget($this->store_collapse_target)
            ->expand();
    }

    private function applyCollapsedState(): void
    {
        if ($this->store_collapsed_panel === 'first') {
            $this->split_workspace->collapseFirst();
            return;
        }

        if ($this->store_collapsed_panel === 'second') {
            $this->split_workspace->collapseSecond();
            return;
        }

        $this->split_workspace->expand();
    }
}
