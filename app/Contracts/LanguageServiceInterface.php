<?php

namespace App\Contracts;

interface LanguageServiceInterface
{
    /**
     * Get all available languages as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getLanguages(): array;
}
