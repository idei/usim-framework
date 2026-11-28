<?php

declare(strict_types=1);

namespace Idei\Usim\Support;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Support\LayoutLoader\LayoutApplicationContext;
use Idei\Usim\Support\LayoutLoader\LayoutOrchestrator;

/**
 * Loads declarative container layout definitions (.layout.yaml) and applies
 * them to a Container and its child UIElement instances.
 */
class ContainerLayoutLoader
{
    private static ?LayoutOrchestrator $orchestrator = null;

    /**
     * Resolve and parse a YAML layout file.
     *
     * @param  string|null  $path  Explicit path, filename, or null to infer from calling Screen class
     * @param  string|null  $callingContext  Calling Screen class name
     * @param  string|null  $baseDir  Base directory of a parent layout file for relative includes
     * @return array<string, mixed>
     */
    public static function loadFile(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        return self::orchestrator()->fileLoader->loadFile($path, $callingContext, $baseDir);
    }

    /**
     * Resolve and parse a YAML layout file, returning both its definition and resolved filesystem path.
     *
     * @return array{definition: array<string, mixed>, resolvedPath: string}
     */
    public static function loadFileWithMeta(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        return self::orchestrator()->fileLoader->loadFileWithMeta($path, $callingContext, $baseDir);
    }

    /**
     * Determine whether a sibling "<kebab-class-name>.layout.yaml" exists for the given Screen/Layout class
     * (walking concrete parent classes so subclasses can inherit a package layout's YAML definition).
     */
    public static function hasDefaultLayout(?string $callingContext): bool
    {
        return self::orchestrator()->fileLoader->hasDefaultLayout($callingContext);
    }

    /**
     * Resolve the filesystem path for a layout file reference.
     */
    public static function resolvePath(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): string
    {
        return self::orchestrator()->fileLoader->resolvePath($path, $callingContext, $baseDir);
    }

    /**
     * Resolve the effective main layout section from a root definition.
     * Supports either top-level `grid:`/`children:` OR `slots.main` / `slots.center` / `slots.content`.
     * Throws fail-fast if both root `grid:` and `slots.main`/`center`/`content` are declared simultaneously.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function resolveMainDefinition(array $definition): array
    {
        return self::orchestrator()->definitionParser->resolveMainDefinition($definition);
    }

    /**
     * Extract normalized auxiliary slot projections from a root `slots:` section
     * (excluding `main` / `center` / `content`, which map to the Screen's primary container).
     *
     * @param  array<string, mixed>  $definition
     * @return list<array{
     *     slot: string,
     *     container_name: string,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool,
     *     screen: ?string,
     *     params: array<int|string, mixed>,
     *     rule: array<string, mixed>
     * }>
     */
    public static function extractSlotDefinitions(array $definition): array
    {
        return self::orchestrator()->definitionParser->extractSlotDefinitions($definition);
    }

    /**
     * Extract ordered slot names (string identifiers) declared in the layout's children list.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    public static function extractChildSlotNames(array $definition): array
    {
        return self::orchestrator()->definitionParser->extractChildSlotNames($definition);
    }

    /**
     * Extract normalized child rules indexed by slot name or 1-based position.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int|string, array<string, mixed>>
     */
    public static function extractChildRulesBySlot(array $definition): array
    {
        return self::orchestrator()->definitionParser->extractChildRulesBySlot($definition);
    }

    /**
     * Determine whether a child rule declares a nested container layout (inline `grid` or external file reference).
     *
     * @param  array<string, mixed>  $rule
     */
    public static function isNestedContainerRule(array $rule): bool
    {
        return self::orchestrator()->definitionParser->isNestedContainerRule($rule);
    }

    /**
     * Extract an external layout file reference from a child rule (`layout_file`, `include`, `file`, or non-enum `layout`).
     *
     * @param  array<string, mixed>  $rule
     */
    public static function extractExternalLayoutReference(array $rule): ?string
    {
        return self::orchestrator()->definitionParser->extractExternalLayoutReference($rule);
    }

    /**
     * Resolve a concrete UIElement class from an explicit `component:` rule or conventional name prefix.
     *
     * @param  array<string, mixed>  $rule
     * @return class-string<UIElement>|null
     */
    public static function resolveComponentClassForSlot(string $slotName, array $rule = []): ?string
    {
        return self::orchestrator()->componentResolver->resolveComponentClassForSlot($slotName, $rule);
    }

    /**
     * Auto-instantiate a UIElement for a slot using its explicit `component:` or conventional name prefix.
     *
     * @param  array<string, mixed>  $rule
     */
    public static function instantiateElementForSlot(string $slotName, array $rule = []): ?UIElement
    {
        return self::orchestrator()->componentResolver->instantiateElementForSlot($slotName, $rule);
    }

    /**
     * Extract normalized timer entries from a root `timers:` definition.
     *
     * @param  array<string, mixed>  $definition
     * @return list<array{name: string, rule: array<string, mixed>}>
     */
    public static function extractTimerEntries(array $definition): array
    {
        return self::orchestrator()->definitionParser->extractTimerEntries($definition);
    }

    /**
     * Normalize a child entry from the YAML definition into [slot, rule].
     *
     * Supports:
     * - "input_name" (bare string)
     * - ["btn_submit" => ["span" => 1, ...]] (key-value pair)
     * - ["btn_submit" => null] (key without options)
     * - ["slot" => "btn_submit", "span" => 1, ...] (legacy explicit slot)
     *
     * @param  mixed  $key  Key from the children array (index or associative slot name)
     * @param  mixed  $entry  Raw entry from YAML definition
     * @param  int  $position  1-based position in children list
     * @return array{slot: string|int, rule: array<string, mixed>}
     */
    public static function normalizeChildEntry(mixed $key, mixed $entry, int $position): array
    {
        return self::orchestrator()->definitionParser->normalizeChildEntry($key, $entry, $position);
    }

    /**
     * Apply container-level settings immediately (layout type, columns, gap, padding, etc.).
     *
     * @param  array<string, mixed>  $definition
     */
    public static function applyContainerConfig(Container $container, array $definition): void
    {
        self::orchestrator()->applyContainerConfig($container, $definition);
    }

    /**
     * Apply both container-level and child-level layout rules to a Container and its current children.
     *
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $visitedPaths
     */
    public static function applyToContainer(
        Container $container,
        array $definition,
        ?string $callingContext = null,
        bool $autoTranslate = false,
        ?string $baseDir = null,
        array $visitedPaths = []
    ): void {
        self::orchestrator()->applyToContainer(
            $container,
            $definition,
            new LayoutApplicationContext(
                callingContext: $callingContext,
                autoTranslate: $autoTranslate,
                baseDir: $baseDir,
                visitedPaths: $visitedPaths,
            ),
        );
    }

    private static function orchestrator(): LayoutOrchestrator
    {
        return self::$orchestrator ??= LayoutOrchestrator::createDefault();
    }
}
