<?php

namespace Shahnewaz\RedprintNg\Providers;

use Illuminate\Support\ServiceProvider;
use Shahnewaz\RedprintNg\Services\RedprintshipService;

class RedprintshipServiceProvider extends ServiceProvider
{
    /** 
    * This provider cannot be deferred since it loads routes.
    * If deferred, run `php artisan route:cache`
    **/
    protected $defer = false;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot () {
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Register Redprintship Service
        $this->app->singleton('redprintship', function ($app) {
            return new RedprintshipService;
        });
    }

    // Facade provider
    public function provides () {
        return ['redprintship'];
    }
}
