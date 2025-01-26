<?php

namespace Shahnewaz\RedprintNg;

use Illuminate\Support\ServiceProvider;
use Shahnewaz\RedprintNg\Commands\MakeCrudCommand;
use Shahnewaz\RedprintNg\Commands\MakeVueCommand;

class RedprintServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeCrudCommand::class,
                MakeVueCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/config/redprint.php' => config_path('redprint.php'),
            ], 'redprint-config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/redprint.php', 'redprint'
        );
    }
}
