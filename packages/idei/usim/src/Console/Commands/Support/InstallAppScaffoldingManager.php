<?php

namespace Idei\Usim\Console\Commands\Support;

use Idei\Usim\Support\CodeModifier\ClassModifier;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InstallAppScaffoldingManager
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @param array<string, string|bool> $context
     */
    public function installCoreScreens(
        array $context,
        callable $publishStub,
        callable $line,
        callable $installTranslationManagerScaffolding
    ): void {
        $this->installScreen('Home.php.stub', 'Home.php', null, $context, $publishStub, $line);
        $this->installScreen('Menu.php.stub', 'Menu.php', null, $context, $publishStub, $line);
        $this->installScreen('Admin/UsersManager.php.stub', 'UsersManager.php', 'Admin', $context, $publishStub, $line);

        $installTranslationManagerScaffolding();
    }

    /**
     * @param array<string, string|bool> $context
     */
    public function installAuthScaffolding(
        array $context,
        callable $publishStub,
        callable $line,
        callable $info,
        callable $newLine,
        callable $installMigrations,
        callable $installUsersConfigNotice
    ): void {
        $newLine();
        $info('Installing Auth services...');
        $this->installAuthServices($context, $publishStub, $line);

        $newLine();
        $info('Installing Auth screens...');
        $this->installScreen('Auth/Login.php.stub', 'Login.php', 'Auth', $context, $publishStub, $line);
        $this->installScreen('Auth/ForgotPassword.php.stub', 'ForgotPassword.php', 'Auth', $context, $publishStub, $line);
        $this->installScreen('Auth/ResetPassword.php.stub', 'ResetPassword.php', 'Auth', $context, $publishStub, $line);
        $this->installScreen('Auth/EmailVerified.php.stub', 'EmailVerified.php', 'Auth', $context, $publishStub, $line);
        $this->installScreen('Auth/Profile.php.stub', 'Profile.php', 'Auth', $context, $publishStub, $line);

        $newLine();
        $info('Installing Modal components...');
        $this->installComponent('Modals/LoginDialog.php.stub', 'LoginDialog.php', 'Modals', $context, $publishStub, $line);
        $this->installComponent('Modals/RegisterDialog.php.stub', 'RegisterDialog.php', 'Modals', $context, $publishStub, $line);
        $this->installComponent('Modals/EditUserDialog.php.stub', 'EditUserDialog.php', 'Modals', $context, $publishStub, $line);
        $this->installComponent('Modals/EditTranslationDialog.php.stub', 'EditTranslationDialog.php', 'Modals', $context, $publishStub, $line);

        $newLine();
        $info('Installing DataTable components...');
        // TODO: A ESTO HAY QUE MOFIFICARLO
        // $this->installComponent('DataTable/UserTableModel.php.stub', 'UserTableModel.php', 'DataTable', $context, $publishStub, $line);

        $newLine();
        $info('Installing AuthController...');
        $this->installAuthController($context, $publishStub, $line);

        $newLine();
        $info('Configuring User model...');
        $this->configureUserModel($context, $publishStub, $line);

        $newLine();
        $info('Installing EventServiceProvider...');
        $this->installEventServiceProvider($context, $publishStub, $line);
        $this->registerBootstrapProviders($line);

        $newLine();
        $info('Publishing migrations...');
        $installMigrations();

        $newLine();
        $info('Publishing users config...');
        $installUsersConfigNotice();

        $newLine();
        $info('Publishing test stubs...');
        $this->installTestStubs($context, $publishStub, $line);
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installScreen(
        string $stubName,
        string $targetName,
        ?string $subdirectory,
        array $context,
        callable $publishStub,
        callable $line
    ): void {
        $stubsBasePath = (string) $context['stubsBasePath'];
        $screensPath = (string) $context['screensPath'];
        $screensNamespace = (string) $context['screensNamespace'];
        $componentsNamespace = (string) $context['componentsNamespace'];
        $userModelImport = (string) $context['userModelImport'];
        $userModelClass = (string) $context['userModelClass'];

        $stubPath = $stubsBasePath . '/screens/' . $stubName;
        $targetDir = $subdirectory ? $screensPath . '/' . $subdirectory : $screensPath;
        $targetFile = $targetDir . '/' . $targetName;
        $namespace = $subdirectory
            ? $screensNamespace . '\\' . str_replace('/', '\\', $subdirectory)
            : $screensNamespace;

        $publishStub($stubPath, $targetFile, false, [
            '{{ namespace }}' => $namespace,
            '{{ screensNamespace }}' => $screensNamespace,
            '{{ componentsNamespace }}' => $componentsNamespace,
            '{{ userModel }}' => $userModelImport,
            '{{ userModelClass }}' => $userModelClass,
        ]);

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installComponent(
        string $stubName,
        string $targetName,
        ?string $subdirectory,
        array $context,
        callable $publishStub,
        callable $line
    ): void {
        $stubsBasePath = (string) $context['stubsBasePath'];
        $componentsPath = (string) $context['componentsPath'];
        $componentsNamespace = (string) $context['componentsNamespace'];

        $stubPath = $stubsBasePath . '/components/' . $stubName;
        $targetDir = $subdirectory ? $componentsPath . '/' . $subdirectory : $componentsPath;
        $targetFile = $targetDir . '/' . $targetName;
        $namespace = $subdirectory
            ? $componentsNamespace . '\\' . str_replace('/', '\\', $subdirectory)
            : $componentsNamespace;

        $publishStub($stubPath, $targetFile, false, [
            '{{ componentsNamespace }}' => $namespace,
        ]);

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installAuthServices(array $context, callable $publishStub, callable $line): void
    {
        $this->installService('Auth/AuthSessionService.php.stub', 'AuthSessionService.php', 'Auth', $context, $publishStub, $line);
        $this->installService('Auth/LoginService.php.stub', 'LoginService.php', 'Auth', $context, $publishStub, $line);
        $this->installService('Auth/RegisterService.php.stub', 'RegisterService.php', 'Auth', $context, $publishStub, $line);
        $this->installService('Auth/PasswordService.php.stub', 'PasswordService.php', 'Auth', $context, $publishStub, $line);
        $this->installService('User/UserService.php.stub', 'UserService.php', 'User', $context, $publishStub, $line);
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installService(
        string $stubName,
        string $targetName,
        ?string $subdirectory,
        array $context,
        callable $publishStub,
        callable $line
    ): void {
        $stubsBasePath = (string) $context['stubsBasePath'];
        $userModelImport = (string) $context['userModelImport'];
        $userModelClass = (string) $context['userModelClass'];

        $stubPath = $stubsBasePath . '/services/' . $stubName;
        $targetDir = $subdirectory ? app_path('Services/' . $subdirectory) : app_path('Services');
        $targetFile = $targetDir . '/' . $targetName;
        $namespace = $subdirectory
            ? 'App\\Services\\' . str_replace('/', '\\', $subdirectory)
            : 'App\\Services';

        $publishStub($stubPath, $targetFile, false, [
            '{{ namespace }}' => $namespace,
            '{{ userModel }}' => $userModelImport,
            '{{ userModelClass }}' => $userModelClass,
        ]);

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installTestStubs(array $context, callable $publishStub, callable $line): void
    {
        $this->installTestFile('Support/usim_bootstrap.php.stub', 'usim_bootstrap.php', 'Support', $context, $publishStub, $line);
        $this->installTestFile('Traits/UsimTestHelpers.php.stub', 'UsimTestHelpers.php', 'Traits', $context, $publishStub, $line);
        $this->installTestFile(
            'Pest.php.stub',
            'Pest.php',
            null,
            $context,
            $publishStub,
            $line,
            function (string $path): void {
                $this->addRequireToPest($path);
            }
        );
        $this->installTestFile(
            'TestCase.php.stub',
            'TestCase.php',
            null,
            $context,
            $publishStub,
            $line,
            function (string $path) use ($line): void {
                $this->addUsimTestHelpersToTestCase($path, $line);
            }
        );

        $this->installTestFile('Support/UiScreenTestHelpers.php.stub', 'UiScreenTestHelpers.php', 'Support', $context, $publishStub, $line);
        $this->installTestFile('Support/UiMemoryRenderer.php.stub', 'UiMemoryRenderer.php', 'Support', $context, $publishStub, $line);
        $this->installTestFile('Support/UiComponentRef.php.stub', 'UiComponentRef.php', 'Support', $context, $publishStub, $line);
        $this->installTestFile('Support/UiScenario.php.stub', 'UiScenario.php', 'Support', $context, $publishStub, $line);
        $this->installTestFile('Support/UiPayloadHelpers.php.stub', 'UiPayloadHelpers.php', 'Support', $context, $publishStub, $line);

        $this->installTestFile('Feature/HomeMenuScreenTest.php.stub', 'HomeMenuScreenTest.php', 'Feature', $context, $publishStub, $line);
        $this->installTestFile('Feature/LoginScreenTest.php.stub', 'LoginScreenTest.php', 'Feature', $context, $publishStub, $line);
        $this->installTestFile('Feature/PasswordRecoveryUiTest.php.stub', 'PasswordRecoveryUiTest.php', 'Feature', $context, $publishStub, $line);
        $this->installTestFile('Feature/UiAuthEventsContractTest.php.stub', 'UiAuthEventsContractTest.php', 'Feature', $context, $publishStub, $line);
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installTestFile(
        string $stubName,
        string $targetName,
        ?string $subdirectory,
        array $context,
        callable $publishStub,
        callable $line,
        ?callable $postInstallCallback = null
    ): void {
        $stubsBasePath = (string) $context['stubsBasePath'];
        $userModelImport = (string) $context['userModelImport'];
        $userModelClass = (string) $context['userModelClass'];

        $stubPath = $stubsBasePath . '/tests/' . $stubName;
        $targetDir = $subdirectory ? base_path('tests/' . $subdirectory) : base_path('tests');
        $targetFile = $targetDir . '/' . $targetName;

        $publishStub($stubPath, $targetFile, false, [
            '{{ userModel }}' => $userModelImport,
            '{{ userModelClass }}' => $userModelClass,
        ], $postInstallCallback);

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    private function addUsimTestHelpersToTestCase(string $path, callable $line): void
    {
        $line('  <fg=green>✓</> Adding traits to TestCase.php...');
        ClassModifier::addTraitToClass($path, 'TestCase', RefreshDatabase::class);
        ClassModifier::addTraitToClass($path, 'TestCase', 'Tests\\Traits\\UsimTestHelpers');
    }

    private function addRequireToPest(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return;
        }

        $line = "require_once __DIR__ . '/Support/usim_bootstrap.php';";
        if (str_contains($content, $line)) {
            return;
        }

        $updatedContent = preg_replace(
            '/<\?php\s*/',
            "<?php\n\n{$line}\n\n",
            $content,
            1
        );

        if (!is_string($updatedContent)) {
            return;
        }

        file_put_contents($path, $updatedContent);
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installAuthController(array $context, callable $publishStub, callable $line): void
    {
        $stubsBasePath = (string) $context['stubsBasePath'];
        $userModelImport = (string) $context['userModelImport'];
        $userModelClass = (string) $context['userModelClass'];

        $controllerPath = app_path('Http/Controllers/Api/AuthController.php');
        $stubPath = $stubsBasePath . '/controllers/AuthController.php.stub';

        $publishStub($stubPath, $controllerPath, false, [
            '{{ namespace }}' => 'App\\Http\\Controllers\\Api',
            '{{ userModel }}' => $userModelImport,
            '{{ userModelClass }}' => $userModelClass,
        ]);

        $relativePath = str_replace(base_path() . '/', '', $controllerPath);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function configureUserModel(array $context, callable $publishStub, callable $line): void
    {
        $stubsBasePath = (string) $context['stubsBasePath'];

        $userModelPath = app_path('Models/User.php');

        if (!$this->files->exists($userModelPath)) {
            $stubPath = $stubsBasePath . '/models/User.php.stub';

            $publishStub($stubPath, $userModelPath, false, [
                '{{ namespace }}' => 'App\\Models',
            ]);

            $line('  <fg=green>✓</> User model created with USIM auth defaults');
            return;
        }

        ClassModifier::addTraitToClass($userModelPath, 'User', \Laravel\Sanctum\HasApiTokens::class);
        ClassModifier::addTraitToClass($userModelPath, 'User', \Spatie\Permission\Traits\HasRoles::class);
        ClassModifier::addTraitToClass($userModelPath, 'User', \Idei\Usim\Traits\UsimUser::class);

        ClassModifier::addInterface($userModelPath, 'User', \Illuminate\Contracts\Auth\MustVerifyEmail::class);
        ClassModifier::addInterface($userModelPath, 'User', \Illuminate\Contracts\Auth\CanResetPassword::class);

        ClassModifier::addPropertyArrayValue($userModelPath, 'User', 'fillable', 'terms_accepted_at');
        ClassModifier::addCast($userModelPath, 'User', 'terms_accepted_at', 'datetime');

        $line('  <fg=green>✓</> User model updated with USIM auth defaults');
    }

    /**
     * @param array<string, string|bool> $context
     */
    private function installEventServiceProvider(array $context, callable $publishStub, callable $line): void
    {
        $stubsBasePath = (string) $context['stubsBasePath'];

        $targetPath = app_path('Providers/EventServiceProvider.php');
        $stubPath = $stubsBasePath . '/providers/EventServiceProvider.php.stub';

        $publishStub($stubPath, $targetPath, false, []);

        $relativePath = str_replace(base_path() . '/', '', $targetPath);
        $line("  <fg=green>✓</> {$relativePath}");
    }

    private function registerBootstrapProviders(callable $line): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (!$this->files->exists($providersPath)) {
            $line('  <fg=yellow>!</> bootstrap/providers.php not found, skipping');
            return;
        }

        $contents = $this->files->get($providersPath);

        if (str_contains($contents, 'EventServiceProvider')) {
            $line('  <fg=blue>→</> EventServiceProvider already in bootstrap/providers.php');
            return;
        }

        $pos = strrpos($contents, '];');
        if ($pos !== false) {
            $contents = substr($contents, 0, $pos)
                . "    App\\Providers\\EventServiceProvider::class,\n];"
                . substr($contents, $pos + 2);

            $this->files->put($providersPath, $contents);
            $line('  <fg=green>✓</> EventServiceProvider registered in bootstrap/providers.php');
        }
    }
}
