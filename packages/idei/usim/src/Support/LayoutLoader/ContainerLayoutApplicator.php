<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;

/**
 * Narrow contract allowing child layout appliers to recurse into nested containers
 * without circular constructor dependencies.
 */
interface ContainerLayoutApplicator
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function applyToContainer(
        Container $container,
        array $definition,
        LayoutApplicationContext $context,
    ): void;
}
