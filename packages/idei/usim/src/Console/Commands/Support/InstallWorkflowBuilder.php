<?php

namespace Idei\Usim\Console\Commands\Support;

class InstallWorkflowBuilder
{
    /**
     * @param callable(): void $checkEnvironment
     * @param callable(): void $checkDatabaseReadiness
     * @param callable(): void $publishConfig
     * @param callable(): void $publishAssets
     * @param callable(): void $installCoreScreens
     * @param callable(): void $installAuthScaffolding
     * @param callable(): void $installViews
     * @param callable(): void $installLanguageStubs
     * @param callable(): void $installWebRoutes
     * @param callable(): void $appendEnvVars
     * @param callable(): void $configureRoot
     * @param callable(): void $registerHelpers
     * @param callable(): void $upsertAccessAndLanguages
     * @param callable(): void $discoverScreens
     * @return array<int, array{key:string,label:string,run:callable():void}>
     */
    public function build(
        callable $checkEnvironment,
        callable $checkDatabaseReadiness,
        callable $publishConfig,
        callable $publishAssets,
        callable $installCoreScreens,
        callable $installAuthScaffolding,
        callable $installViews,
        callable $installLanguageStubs,
        callable $installWebRoutes,
        callable $appendEnvVars,
        callable $configureRoot,
        callable $registerHelpers,
        callable $upsertAccessAndLanguages,
        callable $discoverScreens
    ): array {
        return [
            ['key' => 'safety.check-environment', 'label' => 'Checking environment safety', 'run' => $checkEnvironment],
            ['key' => 'database.check-readiness', 'label' => 'Checking database migration readiness', 'run' => $checkDatabaseReadiness],
            ['key' => 'bootstrap.publish-config', 'label' => 'Publishing USIM config', 'run' => $publishConfig],
            ['key' => 'bootstrap.publish-assets', 'label' => 'Publishing USIM assets', 'run' => $publishAssets],
            ['key' => 'scaffold.install-screens', 'label' => 'Installing core screens', 'run' => $installCoreScreens],
            ['key' => 'scaffold.install-auth', 'label' => 'Installing auth scaffolding', 'run' => $installAuthScaffolding],
            ['key' => 'scaffold.install-views', 'label' => 'Installing views', 'run' => $installViews],
            ['key' => 'scaffold.install-lang-stubs', 'label' => 'Publishing language stubs', 'run' => $installLanguageStubs],
            ['key' => 'routing.install-web-routes', 'label' => 'Installing web routes', 'run' => $installWebRoutes],
            ['key' => 'env.append-usim-vars', 'label' => 'Ensuring USIM env variables', 'run' => $appendEnvVars],
            ['key' => 'env.configure-root-user', 'label' => 'Configuring root user credentials', 'run' => $configureRoot],
            ['key' => 'autoload.register-helpers', 'label' => 'Registering package helper autoload', 'run' => $registerHelpers],
            ['key' => 'database.upsert-usim-access', 'label' => 'Upserting permissions, roles, users and languages', 'run' => $upsertAccessAndLanguages],
            ['key' => 'discover.screens', 'label' => 'Discovering screens', 'run' => $discoverScreens],
        ];
    }
}
