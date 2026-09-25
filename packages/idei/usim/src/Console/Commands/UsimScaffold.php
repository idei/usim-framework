<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Support\UsimConfig;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UsimScaffold extends Command
{
    protected $signature = 'usim:scaf
        {name : The name of the screen to scaffold}
        {--type=basic : The template to use}
        {--force : Overwrite existing files if they exist}';

    protected $description = 'Scaffold a new screen class with the specified name and template.';

    public function __construct(protected UsimConfig $config, protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    public function handle()
    {
        if ($this->option('help')) {
            $this->line($this->getHelp());
            return 0;
        }

        $this->info('Scaffolding a new screen...');
        $name = $this->argument('name');
        $type = $this->option('type');
        $namespace = $this->config->screensNamespace;
        $force = $this->option('force');

        $stubPath = $this->stubsPath("scaffolding/{$type}.php.stub");
        $this->info("Using stub: {$stubPath}");
        if (!file_exists($stubPath)) {
            $this->error("Stub for type '{$type}' does not exist.");
            return 1;
        }

        $name = $this->pascalize($name);
        $className = $name;
        $filePath = app_path('UI/Screens/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($filePath) && !$force) {
            $this->error("File '{$filePath}' already exists.");
            return 1;
        }

        $stubContent = file_get_contents($stubPath);
        $stubContent = str_replace('{{ name }}', $name, $stubContent);
        $stubContent = str_replace('{{ namespace }}', $namespace, $stubContent);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $stubContent);
        $this->info("Screen '{$className}' has been scaffolded at '{$filePath}'.");
        $this->info("Don't forget to make php artisan usim:discover to register the new screen.");
        return 0;
    }

    public function stubsPath(string $path = ''): string
    {
        return dirname(__DIR__, 3) . '/stubs/' . ltrim($path, '/\\');
    }

    private function pascalize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }
}
