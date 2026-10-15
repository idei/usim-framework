<?php

namespace App\Contracts;

interface CountryServiceInterface
{
    /**
     * Get all available countries as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getCountries(): array;

    /**
     * Resolve a two-letter ISO country code to its English country name.
     */
    public function getCountryName(string $countryCode): string;
}
