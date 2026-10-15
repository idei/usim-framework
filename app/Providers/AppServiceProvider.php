<?php

namespace App\Providers;

use App\Contracts\CityServiceInterface;
use App\Contracts\CountryServiceInterface;
use App\Contracts\LanguageServiceInterface;
use App\Contracts\StateServiceInterface;
use App\Services\City\CityService;
use App\Services\Country\CountryService;
use App\Services\Language\LanguageService;
use App\Services\State\StateService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CountryServiceInterface::class, CountryService::class);
        $this->app->singleton(StateServiceInterface::class, StateService::class);
        $this->app->singleton(CityServiceInterface::class, CityService::class);
        $this->app->singleton(LanguageServiceInterface::class, LanguageService::class);

        $this->app->alias(CountryServiceInterface::class, \App\Services\Country\CountryServiceInterface::class);
        $this->app->alias(StateServiceInterface::class, \App\Services\State\StateServiceInterface::class);
        $this->app->alias(CityServiceInterface::class, \App\Services\City\CityServiceInterface::class);
        $this->app->alias(LanguageServiceInterface::class, \App\Services\Language\LanguageServiceInterface::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
