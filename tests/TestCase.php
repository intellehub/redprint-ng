<?php

namespace Shahnewaz\RedprintNg\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

class TestCase extends BaseTestCase
{
    protected $testFilesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temp directory if it doesn't exist
        $this->testFilesPath = __DIR__ . '/../temp_files';
        if (!file_exists($this->testFilesPath)) {
            mkdir($this->testFilesPath, 0777, true);
        }

        $this->createDirectories();
        $this->createMockPackageJson();
    }

    protected function createDirectories()
    {
        $directories = [
            $this->testFilesPath . '/app/Models',
            $this->testFilesPath . '/app/Http/Controllers',
            $this->testFilesPath . '/app/Http/Resources',
            $this->testFilesPath . '/resources/js/pages',
            $this->testFilesPath . '/resources/js/components',
            $this->testFilesPath . '/resources/js/layouts',
            $this->testFilesPath . '/resources/js/router',
            $this->testFilesPath . '/database/migrations',
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0777, true);
            }
        }
    }

    protected function createMockPackageJson()
    {
        $packageJson = [
            'dependencies' => [
                'vue' => '^3.0.0',
                'vue-router' => '^4.0.0'
            ],
            'devDependencies' => [
                '@vitejs/plugin-vue' => '^4.0.0',
                'vite' => '^4.0.0'
            ]
        ];

        File::put($this->testFilesPath . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));
    }

    protected function getPackageProviders($app)
    {
        return [
            'Shahnewaz\RedprintNg\Providers\RedprintServiceProvider'
        ];
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