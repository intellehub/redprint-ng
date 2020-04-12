<?php

namespace Shahnewaz\Redprint\Providers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\ServiceProvider;
use Shahnewaz\Redprint\Console\Commands\Clean;
use Shahnewaz\Redprint\Services\RedprintService;

class RedprintServiceProvider extends ServiceProvider
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
    public function boot (Factory $view) {

        if ($this->app->runningInConsole()) {
            $this->commands([
                Clean::class
            ]);
        }
        $this->load();
        $this->publish();
        $this->registerViewComposers($view);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Use default config from package
        $this->mergeConfigFrom($this->packagePath('config/menu.php'), 'menu');
        $this->mergeConfigFrom($this->packagePath('config/redprint.php'), 'redprint');
        $this->mergeConfigFrom($this->packagePath('config/redprint_license.php'), 'redprint_license');

        // Add route middlewares
        $this->app['router']->aliasMiddleware('redprint', \Shahnewaz\Redprint\Http\Middleware\Redprint::class);

        // Register Redprint Service
        $this->app->singleton('redprint', function ($app) {
            return new RedprintService;
        });

        // THIRD PARTY PROVIDERS
        /**
         * DOT ENV EDITOR
         * */
        $this->app->register(
            'Jackiedo\DotenvEditor\DotenvEditorServiceProvider'
        );
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('DotenvEditor', 'Jackiedo\DotenvEditor\Facades\DotenvEditor');
    }

    // Facade provider
    public function provides () {
        return ['redprint'];
    }

    // Root path for package files
    private function packagePath($path) {
        return __DIR__."/../../$path";
    }

    // Class loaders for package
    private function load () {
        // Routes
        $this->loadRoutesFrom($this->packagePath('src/routes/web.php'));

        // Translations
        $this->loadTranslationsFrom($this->packagePath('resources/lang'), 'redprint');

        // Views
        $this->loadViewsFrom($this->packagePath('resources/views'), 'redprint');
    }

    // Publish required resouces from package
    private function publish () {

        // Publish Translations
        $this->publishes([
            $this->packagePath('resources/lang') => resource_path('lang/vendor/redprint'),
        ], 'redprint-translations');

        // Publish views
        $this->publishes([
            $this->packagePath('resources/views') => resource_path('views/vendor'),
        ], 'redprint-views');
        
        // Publish assets
        $this->publishes([
            $this->packagePath('resources/assets') => public_path('vendor/redprint'),
        ], 'redprint-assets');

        // Publish Menu Config
        $this->publishes([
            $this->packagePath('config/menu.php') => config_path('menu.php'),
        ], 'redprint-menu');

        // Publish Redprint Config
        $this->publishes([
            $this->packagePath('config/redprint.php') => config_path('redprint.php'),
        ], 'redprint-config');

    }

    /**
     * Register Redprint view composer
     * */
    private function registerViewComposers(Factory $view)
    {
        // Not Needed
    }

}
