<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\UIDifferInterface;

/**
 * Concrete implementation of UIDifferInterface.
 *
 * Compares two UI component trees and returns incremental diffs:
 * - ADD: Component added (includes parent)
 * - UPDATE: Changed properties only
 * - DELETE: Component removed (parent = null)
 */
class ComponentDiffer implements UIDifferInterface
{
    /**
     * Compare two UI component trees and return only changes.
     *
     * @param  array<int|string, array<string, mixed>>  $oldUI
     * @param  array<int|string, array<string, mixed>>  $newUI
     * @return array<int|string, array<string, mixed>>
     */
    public function compare(array $oldUI, array $newUI): array
    {
        $changes = [];

        $oldComponents = $this->flattenComponents($oldUI);
        $newComponents = $this->flattenComponents($newUI);

        foreach ($newComponents as $id => $newComp) {
            if (! isset($oldComponents[$id])) {
                $changes[$id] = $newComp;
            } else {
                $oldParent = $oldComponents[$id]['parent'] ?? null;
                $newParent = $newComp['parent'] ?? null;

                if ($oldParent === null && $newParent !== null) {
                    $changes[$id] = $newComp;
                } else {
                    $diff = $this->diffProperties($oldComponents[$id], $newComp);
                    if (! empty($diff)) {
                        $changes[$id] = $diff;
                    }
                }
            }
        }

        foreach ($oldComponents as $id => $oldComp) {
            if (! isset($newComponents[$id])) {
                $changes[$id] = ['parent' => null];
            }
        }

        return $changes;
    }

    /**
     * Compare two components and return changed properties only.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    protected function diffProperties(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if ($key === 'type' || $key === '_order') {
                continue;
            }

            if (! isset($old[$key]) || $old[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        foreach ($old as $key => $value) {
            if (! isset($new[$key]) && $key !== 'type' && $key !== 'parent' && $key !== '_order') {
                $changes[$key] = null;
            }
        }

        return $changes;
    }

    /**
     * Flatten UI tree into an associative array indexed by component ID.
     *
     * @param  array<int|string, array<string, mixed>>  $ui
     * @return array<int|string, array<string, mixed>>
     */
    protected function flattenComponents(array $ui): array
    {
        $flat = [];

        foreach ($ui as $id => $component) {
            if (is_numeric($id)) {
                $flat[$id] = $component;
            }
        }

        return $flat;
    }
}

