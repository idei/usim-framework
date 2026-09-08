<?php

namespace Idei\Usim\Support\CodeModifier;

/**
 * Neutral description of what a stub class declares, independent of any merge strategy.
 */
final class StubClassSpec
{
    /**
     * @param string[] $imports Fully qualified names imported by the stub
     * @param string[] $traits Fully qualified trait names used by the stub class
     * @param string[] $interfaces Fully qualified interface names implemented by the stub class
     * @param string[] $fillable String values declared in the stub's `$fillable` array
     * @param array<string, string> $casts Attribute => cast type pairs declared by the stub
     * @param string[] $methods Raw source of each public method declared by the stub class
     */
    public function __construct(
        public readonly array $imports,
        public readonly array $traits,
        public readonly array $interfaces,
        public readonly array $fillable,
        public readonly array $casts,
        public readonly array $methods,
    ) {
    }
}
