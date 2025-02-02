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
            $this->commands($this->commands);

            $this->publishes([
                __DIR__ . '/../config/redprint.php' => config_path('redprint.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->app->singleton(MakeCrudCommand::class, function ($app) {
            return new MakeCrudCommand();
        });

        $this->commands($this->commands);

        $configPath = __DIR__ . '/../config/redprint.php';
        $this->mergeConfigFrom($configPath, 'redprint');
    }
}
