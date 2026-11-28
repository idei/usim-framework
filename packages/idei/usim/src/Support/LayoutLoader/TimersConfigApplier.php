<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Timer;

/**
 * Mounts and configures non-visual Timer components declared under the root `timers:` section.
 */
final class TimersConfigApplier
{
    public function __construct(
        private readonly LayoutDefinitionParser $definitionParser,
        private readonly ElementPropertyApplier $propertyApplier,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function apply(Container $container, array $definition, ?string $callingContext = null): void
    {
        $entries = $this->definitionParser->extractTimerEntries($definition);
        if ($entries === []) {
            return;
        }

        $existingByName = $this->indexExistingTimers($container);

        foreach ($entries as $entry) {
            $name = $entry['name'];
            $timer = $existingByName[$name] ?? $this->createAndAttachTimer($container, $name);
            $existingByName[$name] = $timer;

            $this->propertyApplier->applyElementCustomProperties($timer, $entry['rule'], $callingContext);
        }
    }

    /**
     * @return array<string, Timer>
     */
    private function indexExistingTimers(Container $container): array
    {
        $existingByName = [];
        foreach ($container->getChildren() as $child) {
            if ($child instanceof Timer && $child->getName() !== null) {
                $existingByName[$child->getName()] = $child;
            }
        }

        return $existingByName;
    }

    private function createAndAttachTimer(Container $container, string $name): Timer
    {
        $timer = new Timer($name);
        $container->add($timer);

        return $timer;
    }
}
