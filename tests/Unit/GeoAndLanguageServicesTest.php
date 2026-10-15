<?php

use App\Contracts\CityServiceInterface;
use App\Contracts\CountryServiceInterface;
use App\Contracts\LanguageServiceInterface;
use App\Contracts\StateServiceInterface;
use App\Services\City\CityService;
use App\Services\Country\CountryService;
use App\Services\Language\LanguageService;
use App\Services\State\StateService;
use App\UI\Screens\Demo\SelectDemo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| CountryService Tests
|--------------------------------------------------------------------------
*/

it('country service returns cached countries without hitting public API', function () {
    $cachedData = [
        ['value' => 'ar', 'label' => '🇦🇷 Argentina'],
        ['value' => 'uy', 'label' => '🇺🇾 Uruguay'],
    ];

    Cache::put('geo:countries', $cachedData, 86400);
    Http::fake();

    /** @var CountryServiceInterface $service */
    $service = app(CountryServiceInterface::class);
    $result = $service->getCountries();

    expect($result)->toBe($cachedData);
    Http::assertNothingSent();
});

it('country service fetches from API and caches successful response', function () {
    Http::fake([
        'https://restcountries.com/*' => Http::response([
            ['cca2' => 'FR', 'name' => ['common' => 'France'], 'flag' => '🇫🇷'],
            ['cca2' => 'DE', 'name' => ['common' => 'Germany'], 'flag' => '🇩🇪'],
        ], 200),
    ]);

    /** @var CountryService $service */
    $service = app(CountryServiceInterface::class);
    $result = $service->getCountries();

    expect($result)->toHaveCount(2);
    expect($result[0]['value'])->toBe('de');
    expect($result[1]['value'])->toBe('fr');

    // Verify it is cached
    $cached = Cache::get('geo:countries');
    expect($cached)->toBe($result);
});

it('country service falls back gracefully when public API fails', function () {
    Http::fake([
        'https://restcountries.com/*' => Http::response([], 500),
    ]);

    /** @var CountryService $service */
    $service = app(CountryServiceInterface::class);
    $result = $service->getCountries();

    expect($result)->toBeArray()->not->toBeEmpty();
    expect($result)->toBe($service->getFallbacks());
});

it('country service correctly resolves country names and clears cache', function () {
    /** @var CountryService $service */
    $service = app(CountryServiceInterface::class);

    expect($service->getCountryName('us'))->toBe('United States');
    expect($service->getCountryName('ES'))->toBe('Spain');
    expect($service->getCountryName('unknown_code'))->toBe('unknown_code');

    Cache::put('geo:countries', [['value' => 'test', 'label' => 'Test']], 3600);
    expect($service->clearCache())->toBeTrue();
    expect(Cache::has('geo:countries'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| StateService Tests
|--------------------------------------------------------------------------
*/

it('state service returns empty array for blank country code', function () {
    /** @var StateServiceInterface $service */
    $service = app(StateServiceInterface::class);

    expect($service->getStates(''))->toBe([]);
    expect($service->getStates('   '))->toBe([]);
});

it('state service returns cached states without calling public API', function () {
    $cachedStates = [
        ['value' => 'Buenos Aires', 'label' => 'Buenos Aires'],
        ['value' => 'Córdoba', 'label' => 'Córdoba'],
    ];

    Cache::put('geo:states:ar', $cachedStates, 86400);
    Http::fake();

    /** @var StateServiceInterface $service */
    $service = app(StateServiceInterface::class);
    $result = $service->getStates('ar');

    expect($result)->toBe($cachedStates);
    Http::assertNothingSent();
});

it('state service fetches from API and caches result', function () {
    Http::fake([
        'https://countriesnow.space/api/v0.1/countries/states' => Http::response([
            'data' => [
                'states' => [
                    ['name' => 'Madrid'],
                    ['name' => 'Cataluña'],
                ],
            ],
        ], 200),
    ]);

    /** @var StateService $service */
    $service = app(StateServiceInterface::class);
    $result = $service->getStates('es');

    expect($result)->toHaveCount(2);
    expect($result[0]['value'])->toBe('Madrid');

    $cached = Cache::get('geo:states:es');
    expect($cached)->toBe($result);
});

it('state service falls back gracefully when API fails', function () {
    Http::fake([
        'https://countriesnow.space/api/v0.1/countries/states' => Http::response(null, 500),
    ]);

    /** @var StateService $service */
    $service = app(StateServiceInterface::class);
    $result = $service->getStates('us');

    expect($result)->toBeArray()->not->toBeEmpty();
    expect($result[0]['value'])->toBe('California');
});

/*
|--------------------------------------------------------------------------
| CityService Tests
|--------------------------------------------------------------------------
*/

it('city service returns empty array for blank inputs', function () {
    /** @var CityServiceInterface $service */
    $service = app(CityServiceInterface::class);

    expect($service->getCities('', 'California'))->toBe([]);
    expect($service->getCities('us', ''))->toBe([]);
});

it('city service returns cached cities without calling public API', function () {
    $cachedCities = [
        ['value' => 'Los Angeles', 'label' => 'Los Angeles'],
    ];

    $cacheKey = 'geo:cities:us:'.md5('california');
    Cache::put($cacheKey, $cachedCities, 86400);
    Http::fake();

    /** @var CityServiceInterface $service */
    $service = app(CityServiceInterface::class);
    $result = $service->getCities('us', 'California');

    expect($result)->toBe($cachedCities);
    Http::assertNothingSent();
});

it('city service fetches from API and caches result', function () {
    Http::fake([
        'https://countriesnow.space/api/v0.1/countries/state/cities' => Http::response([
            'data' => ['San Francisco', 'Los Angeles'],
        ], 200),
    ]);

    /** @var CityService $service */
    $service = app(CityServiceInterface::class);
    $result = $service->getCities('us', 'California');

    expect($result)->toHaveCount(2);
    expect($result[0]['value'])->toBe('San Francisco');

    $cacheKey = $service->getCacheKey('us', 'California');
    expect(Cache::get($cacheKey))->toBe($result);
});

it('city service falls back gracefully when API fails', function () {
    Http::fake([
        'https://countriesnow.space/api/v0.1/countries/state/cities' => Http::response(null, 500),
    ]);

    /** @var CityService $service */
    $service = app(CityServiceInterface::class);
    $result = $service->getCities('us', 'California');

    expect($result)->toBeArray()->not->toBeEmpty();
    expect($result[0]['value'])->toBe('Los Angeles');
});

/*
|--------------------------------------------------------------------------
| LanguageService Tests
|--------------------------------------------------------------------------
*/

it('language service returns cached languages without calling public API', function () {
    $cachedLanguages = [
        ['value' => 'en', 'label' => 'English'],
        ['value' => 'es', 'label' => 'Spanish'],
    ];

    Cache::put('geo:languages', $cachedLanguages, 86400);
    Http::fake();

    /** @var LanguageServiceInterface $service */
    $service = app(LanguageServiceInterface::class);
    $result = $service->getLanguages();

    expect($result)->toBe($cachedLanguages);
    Http::assertNothingSent();
});

it('language service fetches from API, deduplicates, sorts, and caches', function () {
    Http::fake([
        'https://restcountries.com/*' => Http::response([
            ['languages' => ['spa' => 'Spanish', 'fra' => 'French']],
            ['languages' => ['eng' => 'English', 'spa' => 'Spanish']],
        ], 200),
    ]);

    /** @var LanguageService $service */
    $service = app(LanguageServiceInterface::class);
    $result = $service->getLanguages();

    expect($result)->toHaveCount(3);
    // Alphabetical order: English, French, Spanish
    expect($result[0]['label'])->toBe('English');
    expect($result[1]['label'])->toBe('French');
    expect($result[2]['label'])->toBe('Spanish');

    expect(Cache::get('geo:languages'))->toBe($result);
});

it('language service falls back gracefully when API fails', function () {
    Http::fake([
        'https://restcountries.com/*' => Http::response(null, 500),
    ]);

    /** @var LanguageService $service */
    $service = app(LanguageServiceInterface::class);
    $result = $service->getLanguages();

    expect($result)->toBeArray()->not->toBeEmpty();
    expect($result)->toBe($service->getFallbacks());
});

/*
|--------------------------------------------------------------------------
| SelectDemo Dependency Injection & Delegation Tests
|--------------------------------------------------------------------------
*/

it('select demo uses injected mock services when provided in constructor', function () {
    $mockCountryService = Mockery::mock(CountryServiceInterface::class);
    $mockCountryService->shouldReceive('getCountries')
        ->once()
        ->andReturn([['value' => 'custom', 'label' => 'Custom Country']]);

    $screen = new SelectDemo(countryService: $mockCountryService);
    $countries = $screen->getCountries();

    expect($countries)->toHaveCount(1);
    expect($countries[0]['value'])->toBe('custom');
});
