<?php

namespace App\Services\State;

use App\Contracts\CountryServiceInterface;
use App\Contracts\StateServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StateService implements StateServiceInterface
{
    protected CountryServiceInterface $countryService;

    protected CacheRepository $cache;

    protected int $ttl = 86400; // 24 hours

    protected int $timeout = 3;

    protected string $apiUrl = 'https://countriesnow.space/api/v0.1/countries/states';

    /**
     * Fallback states indexed by ISO-2 country code.
     *
     * @var array<string, list<string>>
     */
    protected array $fallbacks = [
        'us' => ['California', 'New York', 'Texas', 'Florida', 'Illinois'],
        'es' => ['Madrid', 'Cataluña', 'Andalucía', 'Comunidad Valenciana', 'Galicia'],
        'ar' => ['Buenos Aires', 'Córdoba', 'Santa Fe', 'Mendoza', 'San Juan'],
        'mx' => ['Ciudad de México', 'Jalisco', 'Nuevo León', 'Puebla', 'Yucatán'],
        'fr' => ['Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur', 'Occitanie'],
        'de' => ['Bavaria', 'Berlin', 'Hamburg', 'North Rhine-Westphalia', 'Hesse'],
        'it' => ['Lombardy', 'Lazio', 'Campania', 'Veneto', 'Tuscany'],
        'br' => ['São Paulo', 'Rio de Janeiro', 'Bahia', 'Minas Gerais', 'Paraná'],
        'ca' => ['Ontario', 'Quebec', 'British Columbia', 'Alberta'],
        'jp' => ['Tokyo', 'Osaka', 'Kanagawa', 'Aichi', 'Hokkaido'],
    ];

    public function __construct(
        ?CountryServiceInterface $countryService = null,
        ?CacheRepository $cache = null
    ) {
        $this->countryService = $countryService ?? app(CountryServiceInterface::class);
        $this->cache = $cache ?? Cache::store();
    }

    /**
     * Get states or provinces for a given country code as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getStates(string $countryCode): array
    {
        $code = strtolower(trim($countryCode));

        if ($code === '') {
            return [];
        }

        $cacheKey = $this->getCacheKey($code);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && ! empty($cached)) {
            /** @var list<array{value: string, label: string}> $cached */
            return $cached;
        }

        $countryName = $this->countryService->getCountryName($code);
        $states = $this->fetchFromApi($countryName);

        if (! empty($states)) {
            $this->cache->put($cacheKey, $states, $this->ttl);

            return $states;
        }

        return $this->getFallbacksForCountry($code);
    }

    /**
     * Get the cache key for a given country code.
     */
    public function getCacheKey(string $countryCode): string
    {
        return 'geo:states:'.strtolower(trim($countryCode));
    }

    /**
     * Clear cached states for a country.
     */
    public function clearCache(string $countryCode): bool
    {
        return $this->cache->forget($this->getCacheKey($countryCode));
    }

    /**
     * Fetch states from the public CountriesNow API.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function fetchFromApi(string $countryName): array
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->apiUrl, [
                'country' => $countryName,
            ]);

            if ($response->successful()) {
                $states = $response->json('data.states');

                if (is_array($states) && ! empty($states)) {
                    $options = [];

                    foreach ($states as $state) {
                        if (! is_array($state)) {
                            continue;
                        }

                        $name = isset($state['name']) && is_string($state['name'])
                            ? $state['name']
                            : '';

                        if ($name !== '') {
                            $options[] = [
                                'value' => $name,
                                'label' => $name,
                            ];
                        }
                    }

                    if (! empty($options)) {
                        return $options;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("StateService: Unable to fetch states for country [{$countryName}], falling back to local dataset.", [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get fallback states for a country code.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFallbacksForCountry(string $countryCode): array
    {
        $code = strtolower(trim($countryCode));
        $stateList = $this->fallbacks[$code] ?? ['Region 1', 'Region 2', 'Region 3'];

        return array_map(static fn (string $s): array => [
            'value' => $s,
            'label' => $s,
        ], $stateList);
    }
}
