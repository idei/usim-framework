<?php

namespace Idei\Usim\Support\CodeModifier;

/**
 * Applies a {@see StubClassSpec} onto an existing class file using the idempotent
 * per-symbol primitives of {@see ClassModifier}.
 */
class StubClassMerger
{
    public function merge(string $targetPath, string $className, StubClassSpec $spec): void
    {
        foreach ($spec->imports as $import) {
            ClassModifier::addImport($targetPath, $import);
        }

        foreach ($spec->traits as $trait) {
            ClassModifier::addTraitToClass($targetPath, $className, $trait);
        }

        foreach ($spec->interfaces as $interface) {
            ClassModifier::addInterface($targetPath, $className, $interface);
        }

        foreach ($spec->fillable as $field) {
            ClassModifier::addPropertyArrayValue($targetPath, $className, 'fillable', $field);
        }

        foreach ($spec->casts as $field => $type) {
            ClassModifier::addCast($targetPath, $className, $field, $type);
        }

        foreach ($spec->methods as $methodCode) {
            ClassModifier::addMethodToClass($targetPath, $className, $methodCode);
        }
    }
}
