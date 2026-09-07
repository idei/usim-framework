<?php

namespace App\Providers;

use App\Contracts\UnitsServiceContract;
use App\Contracts\UnitTranslationGeneratorContract;
use App\Services\Units\UnitsService;
use App\Services\Units\UnitTranslationGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UnitTranslationGeneratorContract::class, UnitTranslationGenerator::class);
        $this->app->singleton(UnitsServiceContract::class, UnitsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
