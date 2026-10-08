<?php

namespace Idei\Usim\Contracts;

/**
 * Interface for actors/devices that can be paired via PIN/token workflows.
 */
interface PairableActorInterface
{
    /**
     * Determine if this device is currently paired with active credentials.
     */
    public function isPaired(): bool;
}
