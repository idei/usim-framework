<?php

namespace Idei\Usim;

use Idei\Usim\Console\Commands\DiscoverScreensCommand;
use Idei\Usim\Console\Commands\InstallCommand;
use Idei\Usim\Console\Commands\UsimScaffold;
use Idei\Usim\Console\Commands\UsimSyncCommand;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\Contracts\PasswordResetActionInterface;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\Contracts\ScreenAuthorizerInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Contracts\UnitContextResolverInterface;
use Idei\Usim\Contracts\UnitsServiceInterface;
use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Jobs\CleanTemporaryUploadsJob;
use Idei\Usim\Listeners\UsimEventDispatcher;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Support\ComponentDiffer;
use Idei\Usim\Support\ComponentIdGenerator;
use Idei\Usim\Support\DeviceSecurityGuard;
use Idei\Usim\Support\ScreenLifecycleOrchestrator;
use Idei\Usim\Support\SpatieScreenAuthorizer;
use Idei\Usim\Support\Translation\TranslationDatasetQuery;
use Idei\Usim\Support\Translation\TranslationKeyManager;
use Idei\Usim\Support\Translation\TranslationValueResolver;
use Idei\Usim\Support\TranslationService;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateRepository;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\Support\UsimTranslator;
use Idei\Usim\Sync\Handlers\DeviceSyncHandler;
use Idei\Usim\Sync\Handlers\LangSyncHandler;
use Idei\Usim\Sync\Handlers\RoleSyncHandler;
use Idei\Usim\Sync\Handlers\UnitSyncHandler;
use Idei\Usim\Sync\Handlers\UserSyncHandler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;

class UsimServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/usim.php',
            'usim'
        );

        $this->app->scoped(UIChangesCollector::class, function ($app) {
            return new UIChangesCollector;
        });

        $this->app->scoped(
            ComponentIdGeneratorInterface::class,
            ComponentIdGenerator::class
        );

        $this->app->scoped(
            UIStateRepositoryInterface::class,
            UIStateRepository::class
        );

        $this->app->scoped(
            ScreenLifecycleOrchestratorInterface::class,
            ScreenLifecycleOrchestrator::class
        );

        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService(
                $app->make(TranslationKeyManager::class),
                $app->make(TranslationDatasetQuery::class),
                $app->make(TranslationValueResolver::class)
            );
        });

        $this->app->singleton(UsimTranslatorInterface::class, function ($app) {
            return new UsimTranslator(
                $app->make('translator'),
                $app->bound(TranslationService::class) ? $app->make(TranslationService::class) : null
            );
        });

        $this->app->alias(UsimTranslatorInterface::class, UsimTranslator::class);

        $this->app->singleton(UsimConfig::class, function () {
            return new UsimConfig;
        });

        $this->app->singleton(
            UIDifferInterface::class,
            ComponentDiffer::class
        );

        $this->app->singleton(
            ScreenAuthorizerInterface::class,
            SpatieScreenAuthorizer::class
        );

        if (class_exists('App\\Services\\Units\\UnitContextResolver')) {
            $this->app->bind(
                UnitContextResolverInterface::class,
                'App\\Services\\Units\\UnitContextResolver'
            );
        }

        if (class_exists('App\\Services\\Units\\UnitsService')) {
            $this->app->bind(
                UnitsServiceInterface::class,
                'App\\Services\\Units\\UnitsService'
            );
        }

        if (class_exists('App\\Services\\Auth\\LoginService')) {
            $this->app->bind(
                LoginActionInterface::class,
                'App\\Services\\Auth\\LoginService'
            );
        }

        if (class_exists('App\\Services\\Auth\\RegisterService')) {
            $this->app->bind(
                RegisterActionInterface::class,
                'App\\Services\\Auth\\RegisterService'
            );
        }

        if (class_exists('App\\Services\\Auth\\PasswordResetAction')) {
            $this->app->bind(
                PasswordResetActionInterface::class,
                'App\\Services\\Auth\\PasswordResetAction'
            );
        }

        $this->app->singleton(DeviceSecurityGuardInterface::class, function ($app) {
            if (class_exists('App\\Services\\Device\\DeviceSecurityGuard')) {
                return $app->make('App\\Services\\Device\\DeviceSecurityGuard');
            }

            return $app->make(DeviceSecurityGuard::class);
        });

        $this->app->tag([
            RoleSyncHandler::class,
            UnitSyncHandler::class,
            UserSyncHandler::class,
            DeviceSyncHandler::class,
            LangSyncHandler::class,
        ], 'usim.sync_handlers');

        $this->commands([
            DiscoverScreensCommand::class,
            InstallCommand::class,
            UsimSyncCommand::class,
            UsimScaffold::class,
        ]);
    }

    public function boot(Dispatcher $events): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'usim');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'usim');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/idei/usim'),
            ], 'usim-assets');
        }

        // Programar limpieza de archivos temporales (Self-healing maintenance)
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new CleanTemporaryUploadsJob)->hourly();
        });

        // Registrar Evento del Sistema
        $events->listen(UsimEvent::class, UsimEventDispatcher::class);

        // Listener para resetear estado en Octane/RoadRunner
        if (class_exists(RequestReceived::class)) {
            $events->listen(RequestReceived::class, function () {
                UIIdGenerator::reset();
            });
        }

        $this->publishes([
            __DIR__.'/../config/usim.php' => config_path('usim.php'),
        ], 'usim-config');

        // Forzamos a Spatie a usar el modelo de Roles extendido de USIM
        config([
            'permission.models.role' => UsimRole::class,
        ]);

        // Inyectamos dinámicamente los Guards y Providers para los actores de USIM
        $this->injectUsimAuthGuards();
    }

    /**
     * Inyecta los Guards y Providers necesarios para los Actores Polimórficos de USIM
     * sin modificar el archivo físico config/auth.php del usuario.
     */
    protected function injectUsimAuthGuards(): void
    {
        $deviceClass = config('usim.models.device', '\\App\\Models\\Device');

        config([
            'auth.providers.devices' => [
                'driver' => 'eloquent',
                'model' => $deviceClass,
            ],
            'auth.guards.device' => [
                'driver' => 'session',
                'provider' => 'devices',
            ],
        ]);
    }
}
