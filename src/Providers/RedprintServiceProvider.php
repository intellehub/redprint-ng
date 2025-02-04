<?php

namespace Shahnewaz\RedprintNg\Providers;

use Illuminate\Support\ServiceProvider;
use Shahnewaz\RedprintNg\Commands\MakeCrudCommand;
use Shahnewaz\RedprintNg\Commands\MakeVueCommand;

class RedprintServiceProvider extends ServiceProvider
{
    protected $commands = [
        MakeCrudCommand::class,
        MakeVueCommand::class,
    ];

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/redprint.php' => config_path('redprint.php'),
            ], 'config');

            // Register commands
            $this->commands($this->commands);
        }
    }

    public function register()
    {
        // First merge the configs
        $configPath = __DIR__ . '/../config/redprint.php';
        $this->mergeConfigFrom($configPath, 'redprint');

        // Then register commands
        $this->app->singleton(MakeCrudCommand::class, function ($app) {
            return new MakeCrudCommand();
        });
    }
}
