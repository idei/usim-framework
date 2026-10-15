<?php

namespace App\Services\Language;

use App\Contracts\LanguageServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LanguageService implements LanguageServiceInterface
{
    protected CacheRepository $cache;

    protected string $cacheKey = 'geo:languages';

    protected int $ttl = 86400; // 24 hours

    protected int $timeout = 3;

    protected string $apiUrl = 'https://restcountries.com/v3.1/all?fields=languages';

    public function __construct(?CacheRepository $cache = null)
    {
        $this->cache = $cache ?? Cache::store();
    }

    /**
     * Get all available languages as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getLanguages(): array
    {
        $cached = $this->cache->get($this->cacheKey);
        if (is_array($cached) && ! empty($cached)) {
            /** @var list<array{value: string, label: string}> $cached */
            return $cached;
        }

        $languages = $this->fetchFromApi();

        if (! empty($languages)) {
            $this->cache->put($this->cacheKey, $languages, $this->ttl);

            return $languages;
        }

        return $this->getFallbacks();
    }

    /**
     * Clear cached languages.
     */
    public function clearCache(): bool
    {
        return $this->cache->forget($this->cacheKey);
    }

    /**
     * Fetch languages from the public RestCountries API.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function fetchFromApi(): array
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                if (is_array($data) && ! empty($data)) {
                    /** @var array<string, string> $uniqueLanguages */
                    $uniqueLanguages = [];

                    foreach ($data as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $langs = isset($item['languages']) && is_array($item['languages'])
                            ? $item['languages']
                            : [];

                        foreach ($langs as $code => $name) {
                            if (is_string($name)) {
                                $uniqueLanguages[(string) $code] = $name;
                            }
                        }
                    }

                    if (! empty($uniqueLanguages)) {
                        asort($uniqueLanguages);
                        $result = [];

                        foreach ($uniqueLanguages as $code => $name) {
                            $result[] = [
                                'value' => (string) $code,
                                'label' => $name,
                            ];
                        }

                        return $result;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('LanguageService: Unable to fetch languages from remote API, falling back to local dataset.', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Return fallback list of languages.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFallbacks(): array
    {
        return [
            ['value' => 'en', 'label' => 'English'],
            ['value' => 'es', 'label' => 'Spanish'],
            ['value' => 'fr', 'label' => 'French'],
            ['value' => 'de', 'label' => 'German'],
            ['value' => 'it', 'label' => 'Italian'],
            ['value' => 'pt', 'label' => 'Portuguese'],
            ['value' => 'ja', 'label' => 'Japanese'],
            ['value' => 'zh', 'label' => 'Chinese'],
            ['value' => 'ru', 'label' => 'Russian'],
            ['value' => 'ar', 'label' => 'Arabic'],
        ];
    }
}
