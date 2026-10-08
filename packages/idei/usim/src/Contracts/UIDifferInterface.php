<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for calculating structural differences between UI component trees.
 */
interface UIDifferInterface
{
    /**
     * Compare two UI trees and return detected changes (add, update, delete).
     *
     * @param  array<int|string, array<string, mixed>>  $oldUI
     * @param  array<int|string, array<string, mixed>>  $newUI
     * @return array<int|string, array<string, mixed>>
     */
    public function compare(array $oldUI, array $newUI): array;
}

