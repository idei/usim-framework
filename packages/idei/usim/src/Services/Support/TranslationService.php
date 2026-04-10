<?php

namespace Idei\Usim\Services\Support;

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Models\UsimTextValue;
use Idei\Usim\Services\Support\Translation\TranslationDatasetQuery;
use Idei\Usim\Services\Support\Translation\TranslationKeyManager;
use Idei\Usim\Services\Support\Translation\TranslationValueResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class TranslationService
{
    public function __construct(
        private readonly TranslationKeyManager $keyManager,
        private readonly TranslationDatasetQuery $datasetQuery,
        private readonly TranslationValueResolver $valueResolver
    ) {
    }

    public function upsertLanguage(
        string $code,
        string $name,
        ?string $nativeName = null,
        bool $isActive = true,
        bool $isFallback = false
    ): UsimLanguage {
        return $this->keyManager->upsertLanguage($code, $name, $nativeName, $isActive, $isFallback);
    }

    public function createOrUpdateKey(string $key, array $attributes = []): UsimTextKey
    {
        return $this->keyManager->createOrUpdateKey($key, $attributes);
    }

    public function upsertValue(
        string $key,
        string $languageCode,
        ?string $text,
        ?string $mediaUrl = null,
        ?array $mediaMeta = null,
        ?bool $needsReview = null
    ): UsimTextValue {
        return $this->keyManager->upsertValue($key, $languageCode, $text, $mediaUrl, $mediaMeta, $needsReview);
    }

    public function deleteKey(string $key): bool
    {
        return $this->keyManager->deleteKey($key);
    }

    public function deleteValue(string $key, string $languageCode): bool
    {
        return $this->keyManager->deleteValue($key, $languageCode);
    }

    public function listKeys(?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->keyManager->listKeys($search, $perPage);
    }

    public function listLanguagesDataset(): array
    {
        return $this->datasetQuery->listLanguagesDataset();
    }

    public function listKeyGroupsDataset(): array
    {
        return $this->datasetQuery->listKeyGroupsDataset();
    }

    public function listKeysByLanguageDataset(
        string $languageCode,
        ?string $group = null,
        ?string $filter = null,
        string $sortBy = 'key',
        string $sortDirection = 'asc',
        int $perPage = 50,
        int $page = 1
    ): array {
        return $this->datasetQuery->listKeysByLanguageDataset(
            $languageCode,
            $group,
            $filter,
            $sortBy,
            $sortDirection,
            $perPage,
            $page
        );
    }

    public function getValue(string $key, array $params = [], ?string $languageCode = null): string
    {
        return $this->valueResolver->getValue($key, $params, $languageCode);
    }

    public function getEntry(string $key, ?string $languageCode = null): ?array
    {
        return $this->valueResolver->getEntry($key, $languageCode);
    }

    public function getDirectEntry(string $key, string $languageCode): ?array
    {
        return $this->valueResolver->getDirectEntry($key, $languageCode);
    }

    public function safeGetValue(string $key, array $params = [], ?string $languageCode = null): ?string
    {
        try {
            return $this->getValue($key, $params, $languageCode);
        } catch (QueryException) {
            return null;
        }
    }
}
