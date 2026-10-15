<?php

namespace App\Contracts;

interface CityServiceInterface
{
    /**
     * Get cities for a given country code and state name as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getCities(string $countryCode, string $state): array;
}
