<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\NavTree;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\RichText;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\UIElement;
use Illuminate\Support\Str;

/**
 * Resolves and instantiates concrete UIElement classes for declarative layout slots
 * using explicit `component:` rules or conventional name prefixes.
 */
final class ComponentSlotResolver
{
    /**
     * @var array<string, class-string<UIElement>>
     */
    private const COMPONENT_PREFIX_MAP = [
        'input_' => Input::class,
        'btn_' => Button::class,
        'lbl_' => NewLabel::class,
        'sel_' => Select::class,
        'chk_' => Checkbox::class,
        'txt_' => Textarea::class,
        'rte_' => RichText::class,
        'tbl_' => Table::class,
        'lst_' => ItemList::class,
        'grd_' => CardGrid::class,
        'img_' => Image::class,
        'cal_' => Calendar::class,
        'car_' => Carousel::class,
        'upl_' => Uploader::class,
        'crd_' => Card::class,
        'icrd_' => ItemCard::class,
        'cnt_' => Container::class,
        'tmr_' => Timer::class,
        'nav_' => NavTree::class,
    ];

    /**
     * @var array<string, class-string<UIElement>>
     */
    private const COMPONENT_ALIAS_MAP = [
        'input' => Input::class,
        'button' => Button::class,
        'label' => Label::class,
        'new_label' => NewLabel::class,
        'select' => Select::class,
        'checkbox' => Checkbox::class,
        'textarea' => Textarea::class,
        'rich_text' => RichText::class,
        'richtext' => RichText::class,
        'table' => Table::class,
        'item_list' => ItemList::class,
        'list' => ItemList::class,
        'card_grid' => CardGrid::class,
        'cardgrid' => CardGrid::class,
        'item_card' => ItemCard::class,
        'itemcard' => ItemCard::class,
        'image' => Image::class,
        'calendar' => Calendar::class,
        'carousel' => Carousel::class,
        'uploader' => Uploader::class,
        'card' => Card::class,
        'container' => Container::class,
        'split' => Split::class,
        'timer' => Timer::class,
        'nav_tree' => NavTree::class,
        'navtree' => NavTree::class,
    ];

    /**
     * @param  array<string, mixed>  $rule
     * @return class-string<UIElement>|null
     */
    public function resolveComponentClassForSlot(string $slotName, array $rule = []): ?string
    {
        $explicit = $this->resolveExplicitComponentClass($rule);
        if ($explicit !== null) {
            return $explicit;
        }

        $prefixed = $this->resolvePrefixedComponentClass($slotName);
        if ($prefixed !== null) {
            return $prefixed;
        }

        if ($this->isImplicitContainerRule($rule)) {
            return Container::class;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function instantiateElementForSlot(string $slotName, array $rule = []): ?UIElement
    {
        $class = $this->resolveComponentClassForSlot($slotName, $rule);
        if ($class === null) {
            return null;
        }

        return new $class($slotName);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return class-string<UIElement>|null
     */
    private function resolveExplicitComponentClass(array $rule): ?string
    {
        if (! isset($rule['component']) || ! is_string($rule['component']) || trim($rule['component']) === '') {
            return null;
        }

        $raw = trim($rule['component']);
        $alias = Str::snake($raw);
        if (isset(self::COMPONENT_ALIAS_MAP[$alias])) {
            return self::COMPONENT_ALIAS_MAP[$alias];
        }

        if (class_exists($raw) && is_subclass_of($raw, UIElement::class)) {
            return $raw;
        }

        return null;
    }

    /**
     * @return class-string<UIElement>|null
     */
    private function resolvePrefixedComponentClass(string $slotName): ?string
    {
        $lowerSlot = strtolower(trim($slotName));
        foreach (self::COMPONENT_PREFIX_MAP as $prefix => $class) {
            if (str_starts_with($lowerSlot, $prefix)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function isImplicitContainerRule(array $rule): bool
    {
        return isset($rule['layout_slot']) || isset($rule['slot_aliases']) || isset($rule['screen']);
    }
}
