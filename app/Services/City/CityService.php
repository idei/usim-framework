<?php

namespace App\Services\City;

use App\Contracts\CityServiceInterface;
use App\Contracts\CountryServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CityService implements CityServiceInterface
{
    protected CountryServiceInterface $countryService;

    protected CacheRepository $cache;

    protected int $ttl = 86400; // 24 hours

    protected int $timeout = 3;

    protected string $apiUrl = 'https://countriesnow.space/api/v0.1/countries/state/cities';

    /**
     * Fallback cities indexed by normalized state name.
     *
     * @var array<string, list<string>>
     */
    protected array $fallbacks = [
        'california' => ['Los Angeles', 'San Francisco', 'San Diego', 'San Jose', 'Sacramento'],
        'new york' => ['New York City', 'Buffalo', 'Rochester', 'Albany', 'Syracuse'],
        'texas' => ['Houston', 'Austin', 'Dallas', 'San Antonio', 'Fort Worth'],
        'florida' => ['Miami', 'Orlando', 'Tampa', 'Jacksonville', 'Tallahassee'],
        'illinois' => ['Chicago', 'Aurora', 'Naperville', 'Joliet', 'Springfield'],
        'madrid' => ['Madrid', 'Móstoles', 'Alcalá de Henares', 'Fuenlabrada', 'Leganés'],
        'cataluña' => ['Barcelona', 'L\'Hospitalet', 'Badalona', 'Terrassa', 'Sabadell'],
        'andalucía' => ['Sevilla', 'Málaga', 'Córdoba', 'Granada', 'Cádiz'],
        'buenos aires' => ['La Plata', 'Mar del Plata', 'Bahía Blanca', 'Tandil'],
        'córdoba' => ['Córdoba', 'Villa Carlos Paz', 'Río Cuarto', 'San Francisco'],
        'santa fe' => ['Rosario', 'Santa Fe', 'Rafaela', 'Venado Tuerto'],
        'mendoza' => ['Mendoza', 'San Rafael', 'Godoy Cruz', 'Luján de Cuyo'],
        'san juan' => ['San Juan', 'Rawson', 'Rivadavia', 'Chimbas', 'Pocito'],
        'jalisco' => ['Guadalajara', 'Zapopan', 'Puerto Vallarta', 'Tlaquepaque'],
        'nuevo león' => ['Monterrey', 'San Pedro Garza García', 'Guadalupe', 'Apodaca'],
        'ciudad de méxico' => ['Cuauhtémoc', 'Benito Juárez', 'Coyoacán', 'Miguel Hidalgo'],
    ];

    public function __construct(
        ?CountryServiceInterface $countryService = null,
        ?CacheRepository $cache = null
    ) {
        $this->countryService = $countryService ?? app(CountryServiceInterface::class);
        $this->cache = $cache ?? Cache::store();
    }

    /**
     * Get cities for a given country code and state name as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getCities(string $countryCode, string $state): array
    {
        $code = strtolower(trim($countryCode));
        $stateName = trim($state);

        if ($code === '' || $stateName === '') {
            return [];
        }

        $cacheKey = $this->getCacheKey($code, $stateName);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && ! empty($cached)) {
            /** @var list<array{value: string, label: string}> $cached */
            return $cached;
        }

        $countryName = $this->countryService->getCountryName($code);
        $cities = $this->fetchFromApi($countryName, $stateName);

        if (! empty($cities)) {
            $this->cache->put($cacheKey, $cities, $this->ttl);

            return $cities;
        }

        return $this->getFallbacksForState($stateName);
    }

    /**
     * Get the cache key for a given country and state.
     */
    public function getCacheKey(string $countryCode, string $state): string
    {
        return 'geo:cities:'.strtolower(trim($countryCode)).':'.md5(strtolower(trim($state)));
    }

    /**
     * Clear cached cities for a country and state.
     */
    public function clearCache(string $countryCode, string $state): bool
    {
        return $this->cache->forget($this->getCacheKey($countryCode, $state));
    }

    /**
     * Fetch cities from the public CountriesNow API.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function fetchFromApi(string $countryName, string $stateName): array
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->apiUrl, [
                'country' => $countryName,
                'state' => $stateName,
            ]);

            if ($response->successful()) {
                $citiesData = $response->json('data');

                if (is_array($citiesData) && ! empty($citiesData)) {
                    $options = [];

                    foreach (array_slice($citiesData, 0, 30) as $city) {
                        if (! is_string($city) && ! is_numeric($city)) {
                            continue;
                        }

                        $cityStr = (string) $city;
                        if ($cityStr !== '') {
                            $options[] = [
                                'value' => $cityStr,
                                'label' => $cityStr,
                            ];
                        }
                    }

                    if (! empty($options)) {
                        return $options;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("CityService: Unable to fetch cities for [{$countryName}/{$stateName}], falling back to local dataset.", [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get fallback cities for a given state name.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFallbacksForState(string $stateName): array
    {
        $normalizedState = strtolower(trim($stateName));
        $cities = $this->fallbacks[$normalizedState] ?? ['Capital City', 'Central City', 'Coastal City'];

        return array_map(static fn (string $c): array => [
            'value' => $c,
            'label' => $c,
        ], $cities);
    }
}
