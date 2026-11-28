<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\RichText;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Support\Translation\TranslationResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Populates untouched component text properties from the Screen translation dictionary (`i18n: auto`).
 */
final class TranslationAutoHydrator
{
    /**
     * Common USIM component prefixes stripped when resolving convention-based translation keys.
     *
     * @var list<string>
     */
    private const COMPONENT_PREFIXES = [
        'input_',
        'btn_',
        'lbl_',
        'sel_',
        'chk_',
        'txt_',
        'rte_',
        'tbl_',
        'lst_',
        'img_',
        'cal_',
        'car_',
        'upl_',
        'crd_',
        'icrd_',
        'grd_',
        'cnt_',
        'tmr_',
    ];

    /**
     * Ordered component translation schemas matched via instanceof.
     *
     * @var list<array{classes: list<class-string<UIComponent>>, primary: string|null, groups: list<string>, attributes: list<string>}>
     */
    private const COMPONENT_SCHEMAS = [
        [
            'classes' => [NewLabel::class, Label::class],
            'primary' => 'text',
            'groups' => ['labels'],
            'attributes' => ['text', 'tooltip'],
        ],
        [
            'classes' => [Button::class],
            'primary' => 'label',
            'groups' => ['actions', 'buttons'],
            'attributes' => ['label', 'tooltip', 'title'],
        ],
        [
            'classes' => [Input::class, Textarea::class, RichText::class],
            'primary' => 'label',
            'groups' => ['inputs', 'fields'],
            'attributes' => ['label', 'placeholder', 'help_text', 'tooltip'],
        ],
        [
            'classes' => [Select::class],
            'primary' => 'label',
            'groups' => ['selects', 'inputs', 'fields'],
            'attributes' => ['label', 'placeholder', 'help_text', 'empty_message', 'tooltip'],
        ],
        [
            'classes' => [Checkbox::class],
            'primary' => 'label',
            'groups' => ['checkboxes', 'inputs', 'fields'],
            'attributes' => ['label', 'description', 'help_text', 'tooltip'],
        ],
        [
            'classes' => [ItemList::class],
            'primary' => 'title',
            'groups' => ['lists'],
            'attributes' => [
                'title',
                'label',
                'help_text',
                'empty_message',
                'no_results_message',
                'search_placeholder',
                'create_placeholder',
                'create_button_label',
                'bulk_delete_label',
            ],
        ],
        [
            'classes' => [ItemCard::class],
            'primary' => 'title',
            'groups' => ['cards'],
            'attributes' => [
                'title',
                'subtitle',
                'eyebrow',
                'description',
                'badge',
                'footer_text',
            ],
        ],
        [
            'classes' => [CardGrid::class],
            'primary' => 'title',
            'groups' => ['grids', 'cards'],
            'attributes' => [
                'title',
                'subtitle',
                'empty_message',
                'no_results_message',
                'min_chars_message',
                'search_placeholder',
                'load_more_label',
                'clear_filters_label',
            ],
        ],
        [
            'classes' => [Table::class],
            'primary' => 'title',
            'groups' => ['tables'],
            'attributes' => ['title', 'empty_message'],
        ],
        [
            'classes' => [Card::class],
            'primary' => 'title',
            'groups' => ['cards'],
            'attributes' => ['title', 'description'],
        ],
    ];

    /**
     * @var array{primary: string|null, groups: list<string>, attributes: list<string>}
     */
    private const DEFAULT_SCHEMA = [
        'primary' => 'label',
        'groups' => [],
        'attributes' => ['label', 'title', 'text', 'placeholder', 'help_text'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolveTranslationTree(LayoutApplicationContext $context): array
    {
        if (! $context->autoTranslate || $context->callingContext === null) {
            return [];
        }

        return TranslationResolver::resolveScreenTranslationTree($context->callingContext);
    }

    public function autoHydrateContainerChildren(Container $container, string $callingContext): void
    {
        $translationTree = TranslationResolver::resolveScreenTranslationTree($callingContext);
        if ($translationTree === []) {
            return;
        }

        foreach ($container->getChildren() as $child) {
            $slot = ($child instanceof UIComponent || $child instanceof Container) ? $child->getSlot() : null;
            $this->autoHydrateElementTranslations($child, $slot ?? $child->getName(), $translationTree);
        }
    }

    /**
     * @param  array<string, mixed>  $translationTree
     */
    public function autoHydrateElementTranslations(
        UIElement $element,
        int|string|null $slot,
        array $translationTree,
    ): void {
        if (! ($element instanceof UIComponent) || ! is_string($slot) || trim($slot) === '' || ctype_digit(trim($slot))) {
            return;
        }

        $nameCandidates = $this->extractNameCandidates(trim($slot));
        $schema = $this->resolveComponentTranslationSchema($element);

        foreach ($schema['attributes'] as $attrKey) {
            $this->hydrateSingleAttribute($element, $attrKey, $schema, $nameCandidates, $translationTree);
        }
    }

    /**
     * @param  array{primary: string|null, groups: list<string>, attributes: list<string>}  $schema
     * @param  list<string>  $nameCandidates
     * @param  array<string, mixed>  $translationTree
     */
    private function hydrateSingleAttribute(
        UIComponent $element,
        string $attrKey,
        array $schema,
        array $nameCandidates,
        array $translationTree,
    ): void {
        if (! $element->isConfigDefault($attrKey)) {
            return;
        }

        $methodName = Str::camel($attrKey);
        if (! method_exists($element, $methodName)) {
            return;
        }

        $translated = $this->findTranslationValueForAttribute(
            $translationTree,
            $nameCandidates,
            $schema['groups'],
            $attrKey,
            $attrKey === $schema['primary'],
        );

        if ($translated !== null) {
            $element->{$methodName}($translated);
        }
    }

    /**
     * @return array{primary: string|null, groups: list<string>, attributes: list<string>}
     */
    private function resolveComponentTranslationSchema(UIComponent $element): array
    {
        foreach (self::COMPONENT_SCHEMAS as $schema) {
            if ($this->matchesAnyClass($element, $schema['classes'])) {
                return [
                    'primary' => $schema['primary'],
                    'groups' => $schema['groups'],
                    'attributes' => $schema['attributes'],
                ];
            }
        }

        return self::DEFAULT_SCHEMA;
    }

    /**
     * @param  list<class-string<UIComponent>>  $classes
     */
    private function matchesAnyClass(UIComponent $element, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($element instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function extractNameCandidates(string $slotName): array
    {
        $candidates = [$slotName];

        foreach (self::COMPONENT_PREFIXES as $prefix) {
            if (str_starts_with($slotName, $prefix) && strlen($slotName) > strlen($prefix)) {
                $stripped = substr($slotName, strlen($prefix));
                if ($stripped !== '') {
                    $candidates[] = $stripped;
                }
                break;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  list<string>  $nameCandidates
     * @param  list<string>  $groups
     */
    private function findTranslationValueForAttribute(
        array $tree,
        array $nameCandidates,
        array $groups,
        string $attribute,
        bool $isPrimary,
    ): ?string {
        $attributeMatch = $this->findFirstNonEmptyString(
            $tree,
            $this->buildAttributePaths($nameCandidates, $groups, $attribute),
        );

        if ($attributeMatch !== null || ! $isPrimary) {
            return $attributeMatch;
        }

        return $this->findFirstNonEmptyString(
            $tree,
            $this->buildPrimaryPaths($nameCandidates, $groups),
        );
    }

    /**
     * @param  list<string>  $nameCandidates
     * @param  list<string>  $groups
     * @return list<string>
     */
    private function buildAttributePaths(array $nameCandidates, array $groups, string $attribute): array
    {
        $paths = [];
        foreach ($nameCandidates as $name) {
            $paths[] = "{$name}.{$attribute}";
        }

        foreach ($groups as $group) {
            foreach ($nameCandidates as $name) {
                $paths[] = "{$group}.{$name}.{$attribute}";
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $nameCandidates
     * @param  list<string>  $groups
     * @return list<string>
     */
    private function buildPrimaryPaths(array $nameCandidates, array $groups): array
    {
        $paths = $nameCandidates;

        foreach ($groups as $group) {
            foreach ($nameCandidates as $name) {
                $paths[] = "{$group}.{$name}";
            }
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  list<string>  $paths
     */
    private function findFirstNonEmptyString(array $tree, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = Arr::get($tree, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
