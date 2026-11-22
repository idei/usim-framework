<?php

namespace Idei\Usim\Components;

use Idei\Usim\Contracts\UIElement;
use Idei\Usim\ValueObjects\Size;

/**
 * Split container component with draggable and collapsible divider.
 *
 * This component behaves as a specialized container that can host child
 * containers in a two-panel split layout.
 */
class Split extends Container
{
    protected string $type = 'split';

    /**
     * {@inheritDoc}
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public static function deserialize(int $id, array $data): Split
    {
        $split = new self;
        $split->id = $id;

        $type = $data['type'] ?? 'split';
        $split->type = is_string($type) ? $type : 'split';

        $name = $data['name'] ?? null;
        $split->name = is_string($name) ? $name : null;

        $parent = $data['parent'] ?? null;
        $split->parent = is_int($parent) || is_string($parent) || $parent === null ? $parent : null;

        $split->config = array_merge($split->config, $data);

        return $split;
    }

    public function __construct(?string $name = null, ?string $context = null)
    {
        parent::__construct($name, $context);

        $this->type = 'split';
        $this->config['type'] = $this->type;
        $this->config['split_orientation'] = 'horizontal';
        $this->config['split_size'] = '50%';
        $this->config['split_size_target'] = 'first';
        $this->config['splitter_size'] = '8px';
        $this->config['min_first_size'] = '120px';
        $this->config['min_second_size'] = '120px';
        $this->config['draggable'] = true;
        $this->config['collapsible'] = false;
        $this->config['collapse_target'] = 'first';
        $this->config['collapsed_panel'] = null;
        $this->config['collapse_size'] = '0px';
        $this->config['hide_divider_when_collapsed'] = false;
        $this->width(Size::full());
    }

    public function orientation(string $orientation): static
    {
        $normalized = strtolower(trim($orientation));
        $this->config['split_orientation'] = $normalized === 'vertical' ? 'vertical' : 'horizontal';

        return $this;
    }

    public function horizontal(): static
    {
        return $this->orientation('horizontal');
    }

    public function vertical(): static
    {
        return $this->orientation('vertical');
    }

    /**
     * Set panel size (e.g. 30%, 320px). Applies to first pane by default or second pane when splitSizeTarget('second').
     */
    public function splitSize(string $size): static
    {
        $this->config['split_size'] = trim($size);

        return $this;
    }

    /**
     * Choose which pane ('first' or 'second') split_size measures.
     */
    public function splitSizeTarget(string $target): static
    {
        $normalized = strtolower(trim($target));
        $this->config['split_size_target'] = $normalized === 'second' ? 'second' : 'first';

        return $this;
    }

    /**
     * Get the configured split_size value.
     */
    public function getSplitSize(): string
    {
        $size = $this->config['split_size'] ?? '50%';

        return is_string($size) && trim($size) !== '' ? trim($size) : '50%';
    }

    /**
     * Get which pane ('first' or 'second') split_size targets.
     */
    public function getSplitSizeTarget(): string
    {
        $target = $this->config['split_size_target'] ?? 'first';

        return $target === 'second' ? 'second' : 'first';
    }

    /**
     * Set divider thickness (e.g. 8px).
     */
    public function splitterSize(string $size): static
    {
        $this->config['splitter_size'] = trim($size);

        return $this;
    }

    public function minFirstSize(string $size): static
    {
        $this->config['min_first_size'] = trim($size);

        return $this;
    }

    public function minSecondSize(string $size): static
    {
        $this->config['min_second_size'] = trim($size);

        return $this;
    }

    public function draggable(bool $enabled = true): static
    {
        $this->config['draggable'] = $enabled;

        return $this;
    }

    public function collapsible(bool $enabled = true): static
    {
        $this->config['collapsible'] = $enabled;

        return $this;
    }

    public function collapseTarget(string $target): static
    {
        $normalized = strtolower(trim($target));
        $this->config['collapse_target'] = $normalized === 'second' ? 'second' : 'first';

        return $this;
    }

    public function collapseSize(string $size): static
    {
        $this->config['collapse_size'] = trim($size);

        return $this;
    }

    /**
     * Hide the split divider when the target panel is collapsed (useful for 0px right panels).
     */
    public function hideDividerWhenCollapsed(bool $enabled = true): static
    {
        $this->config['hide_divider_when_collapsed'] = $enabled;

        return $this;
    }

    /**
     * Get the currently collapsed panel ('first', 'second', or null).
     */
    public function getCollapsedPanel(): ?string
    {
        $panel = $this->config['collapsed_panel'] ?? null;

        return is_string($panel) && in_array($panel, ['first', 'second'], true) ? $panel : null;
    }

    /**
     * Get the configured collapse target ('first' or 'second').
     */
    public function getCollapseTarget(): string
    {
        $target = $this->config['collapse_target'] ?? 'first';

        return $target === 'second' ? 'second' : 'first';
    }

    /**
     * Force collapsed panel state: first, second, or null.
     */
    public function collapsedPanel(?string $panel): static
    {
        $normalized = $panel === null ? null : strtolower(trim($panel));
        if (! in_array($normalized, ['first', 'second', null], true)) {
            $normalized = null;
        }

        $this->config['collapsed_panel'] = $normalized;

        return $this;
    }

    public function collapseFirst(): static
    {
        return $this->collapsedPanel('first');
    }

    public function collapseSecond(): static
    {
        return $this->collapsedPanel('second');
    }

    public function expand(): static
    {
        return $this->collapsedPanel(null);
    }

    /**
     * Add an element to the first split pane.
     */
    public function addFirst(UIElement $element): static
    {
        $this->firstPaneContainer()->add($element);

        return $this;
    }

    /**
     * Add an element to the second split pane.
     */
    public function addSecond(UIElement $element): static
    {
        $this->secondPaneContainer()->add($element);

        return $this;
    }

    public function firstPane(): Container
    {
        return $this->firstPaneContainer();
    }

    public function secondPane(): Container
    {
        return $this->secondPaneContainer();
    }

    private function firstPaneContainer(): Container
    {
        return $this->ensurePaneContainer('first');
    }

    private function secondPaneContainer(): Container
    {
        return $this->ensurePaneContainer('second');
    }

    private function ensurePaneContainer(string $side): Container
    {
        $existingPanes = array_values(array_filter(
            $this->getChildren(),
            static fn (UIElement $child): bool => $child instanceof Container
        ));

        $index = $side === 'second' ? 1 : 0;
        if (isset($existingPanes[$index])) {
            return $existingPanes[$index];
        }

        $paneNamePrefix = $this->name !== null ? $this->name : ('split_'.$this->id);
        $paneName = sprintf('%s_%s_pane', $paneNamePrefix, $side);
        $pane = new Container($paneName);
        $pane->plain()->width(Size::pct(100))->height(Size::pct(100));
        $this->add($pane);

        return $pane;
    }
}
