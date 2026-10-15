<?php

namespace App\Contracts;

interface StateServiceInterface
{
    /**
     * Get states or provinces for a given country code as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getStates(string $countryCode): array;
}
