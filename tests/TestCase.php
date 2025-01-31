<?php

namespace Shahnewaz\RedprintNg\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shahnewaz\RedprintNg\RedprintServiceProvider;
use Illuminate\Support\Facades\File;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            RedprintServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock package.json with required dependencies
        $packageJson = [
            'dependencies' => [
                'tailwindcss' => '^3.0.0',
                'element-plus' => '^2.0.0',
                'axios' => '^1.0.0',
                'vue' => '^3.0.0',
                'vue-router' => '^4.0.0',
                'vue-i18n' => '^9.0.0',
                'lodash' => '^4.0.0',
            ]
        ];

        // Ensure base directories exist
        $this->createDirectories();

        // Create the package.json file in the test environment
        File::put(base_path('package.json'), json_encode($packageJson, JSON_PRETTY_PRINT));

        // Create a mock Vue router file
        $routerDir = resource_path('js/router');
        if (!File::exists($routerDir)) {
            File::makeDirectory($routerDir, 0755, true);
        }
        
        File::put(resource_path('js/router/routes.ts'), $this->getInitialRouterContent());
    }

    protected function createDirectories()
    {
        $directories = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Http/Resources'),
            resource_path('js/pages'),
            resource_path('js/components'),
            resource_path('js/layouts'),
            resource_path('js/router'),
            database_path('migrations'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
        }
    }

    protected function getInitialRouterContent()
    {
        return <<<'TS'
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    // Existing routes will be added here
]

export default createRouter({
    history: createWebHistory(),
    routes
})
TS;
    }

    protected function tearDown(): void
    {
        // Clean up all test directories
        $directories = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Http/Resources'),
            resource_path('js'),
            database_path('migrations'),
        ];

        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                File::deleteDirectory($directory);
            }
        }

        // Clean up the mock package.json
        if (File::exists(base_path('package.json'))) {
            File::delete(base_path('package.json'));
        }

        parent::tearDown();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Set up any environment configuration
        $app['config']->set('redprint.axios_instance', 'axios');
        $app['config']->set('redprint.vue_router_location', 'js/router/routes.ts');
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
} 