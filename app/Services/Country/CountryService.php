<?php

namespace App\Services\Country;

use App\Contracts\CountryServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CountryService implements CountryServiceInterface
{
    protected CacheRepository $cache;

    protected string $cacheKey = 'geo:countries';

    protected int $ttl = 86400; // 24 hours

    protected int $timeout = 3;

    protected string $apiUrl = 'https://restcountries.com/v3.1/all?fields=cca2,name,flag';

    /**
     * Known country code to English name mapping.
     *
     * @var array<string, string>
     */
    protected array $countryMap = [
        'us' => 'United States',
        'es' => 'Spain',
        'ar' => 'Argentina',
        'mx' => 'Mexico',
        'fr' => 'France',
        'de' => 'Germany',
        'it' => 'Italy',
        'br' => 'Brazil',
        'ca' => 'Canada',
        'jp' => 'Japan',
    ];

    public function __construct(?CacheRepository $cache = null)
    {
        $this->cache = $cache ?? Cache::store();
    }

    /**
     * Get all countries as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getCountries(): array
    {
        $cached = $this->cache->get($this->cacheKey);
        if (\is_array($cached) && ! empty($cached)) {
            /** @var list<array{value: string, label: string}> $cached */
            return $cached;
        }

        $countries = $this->fetchFromApi();

        if (! empty($countries)) {
            $this->cache->put($this->cacheKey, $countries, $this->ttl);

            return $countries;
        }

        return $this->getFallbacks();
    }

    /**
     * Resolve a two-letter ISO country code to its English country name.
     */
    public function getCountryName(string $countryCode): string
    {
        $code = strtolower(trim($countryCode));

        return $this->countryMap[$code] ?? $countryCode;
    }

    /**
     * Clear cached country entries.
     */
    public function clearCache(): bool
    {
        return $this->cache->forget($this->cacheKey);
    }

    /**
     * Fetch countries from the public RestCountries API.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function fetchFromApi(): array
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                if (\is_array($data) && ! empty($data)) {
                    $countries = [];

                    foreach ($data as $item) {
                        if (! \is_array($item)) {
                            continue;
                        }

                        $code = isset($item['cca2']) && is_string($item['cca2'])
                            ? strtolower($item['cca2'])
                            : '';

                        $nameObj = isset($item['name']) && \is_array($item['name'])
                            ? $item['name']
                            : [];

                        $name = isset($nameObj['common']) && is_string($nameObj['common'])
                            ? $nameObj['common']
                            : $code;

                        $flag = isset($item['flag']) && is_string($item['flag'])
                            ? $item['flag']
                            : '';

                        if ($code !== '' && $name !== '') {
                            $countries[] = [
                                'value' => $code,
                                'label' => trim("{$flag} {$name}"),
                            ];
                        }
                    }

                    if (! empty($countries)) {
                        usort($countries, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

                        return $countries;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CountryService: Unable to fetch countries from remote API, falling back to local dataset.', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Return local fallback list of countries.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFallbacks(): array
    {
        return [
            ['value' => 'us', 'label' => '🇺🇸 United States'],
            ['value' => 'es', 'label' => '🇪🇸 Spain'],
            ['value' => 'ar', 'label' => '🇦🇷 Argentina'],
            ['value' => 'mx', 'label' => '🇲🇽 Mexico'],
            ['value' => 'fr', 'label' => '🇫🇷 France'],
            ['value' => 'de', 'label' => '🇩🇪 Germany'],
            ['value' => 'it', 'label' => '🇮🇹 Italy'],
            ['value' => 'br', 'label' => '🇧🇷 Brazil'],
            ['value' => 'ca', 'label' => '🇨🇦 Canada'],
            ['value' => 'jp', 'label' => '🇯🇵 Japan'],
        ];
    }
}
