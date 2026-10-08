<?php

namespace Idei\Usim\Contracts;

/**
 * Standard user contract for USIM UI integration.
 */
interface UsimUserInterface
{
    /**
     * Get the primary identifier for authentication/display.
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the display name for UI rendering.
     */
    public function getDisplayName(): string;

    /**
     * Get the user's primary email.
     */
    public function getEmail(): string;
}
