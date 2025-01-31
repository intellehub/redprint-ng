<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeCrudCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createDirectories();
        $this->copyStubs();
        $this->createRouteFiles();
    }

    protected function createRouteFiles()
    {
        $routesPath = base_path('routes');
        
        if (!File::exists($routesPath)) {
            File::makeDirectory($routesPath, 0777, true);
        }

        // Create api.php if it doesn't exist
        if (!File::exists($routesPath . '/api.php')) {
            File::put($routesPath . '/api.php', $this->getApiRouteStub());
        }
    }

    protected function getApiRouteStub()
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

Route::middleware('api')->group(function () {
    // API routes will be added here
});

PHP;
    }

    public function test_it_requires_model_name()
    {
        $this->artisan('redprint:crud')
             ->assertExitCode(1);
    }

    public function test_it_can_generate_crud()
    {
        $this->withoutMockingConsoleOutput();

        // Define columns directly to bypass interactive prompts
        $columns = [
            [
                'name' => 'title',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'nullable' => false,
                'default' => null,
            ],
            [
                'name' => 'published',
                'type' => 'boolean',
                'nullable' => false,
                'default' => '0',
            ],
        ];

        // Add debug line to see where the error occurs
        $command = $this->app->make('Shahnewaz\RedprintNg\Commands\MakeCrudCommand');
        try {
            $command->setColumns($columns);
            
            $exitCode = Artisan::call('redprint:crud', [
                '--model' => 'Post',
                '--namespace' => 'Blog',
                '--route-prefix' => 'blog',
                '--soft-deletes' => 'true',
            ], null, $command);
        } catch (\Exception $e) {
            fwrite(STDERR, "\nError at line " . $e->getLine() . " in " . $e->getFile() . "\n");
            fwrite(STDERR, $e->getMessage() . "\n");
            throw $e;
        }

        // Get the output for debugging
        $output = Artisan::output();
        fwrite(STDERR, "\nCommand Output:\n" . $output);

        $this->assertEquals(0, $exitCode, "Command failed with output: $output");

        // Assert files were created
        $this->assertFileExists(app_path('Models/Post.php'), 'Model file was not created');
        $this->assertFileExists(app_path('Http/Controllers/Blog/PostController.php'), 'Controller file was not created');
        $this->assertFileExists(app_path('Http/Resources/PostResource.php'), 'Resource file was not created');
        $this->assertFileExists(resource_path('js/pages/Post.vue'), 'Page component was not created');
        $this->assertFileExists(resource_path('js/components/Post/Index.vue'), 'Index component was not created');
        $this->assertFileExists(resource_path('js/components/Post/Form.vue'), 'Form component was not created');
        
        // Assert migration file exists
        $migrationFile = $this->getMigrationFile('create_posts_table');
        $this->assertNotNull($migrationFile, 'Migration file was not created');
        $this->assertFileExists($migrationFile);

        // Verify file contents
        if ($migrationFile) {
            $migrationContent = File::get($migrationFile);
            $this->assertStringContainsString('$table->string(\'title\')', $migrationContent);
            $this->assertStringContainsString('$table->text(\'content\')', $migrationContent);
            $this->assertStringContainsString('$table->boolean(\'published\')', $migrationContent);
        }
    }

    protected function createDirectories()
    {
        $directories = [
            app_path('Models'),
            app_path('Http/Controllers/Blog'),
            app_path('Http/Resources'),
            resource_path('js/pages'),
            resource_path('js/components/Post'),
            resource_path('js/router'),
            database_path('migrations'),
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
        }

        // Create router file
        if (!File::exists(resource_path('js/router/routes.ts'))) {
            File::put(resource_path('js/router/routes.ts'), $this->getRouterContent());
        }

        // Create package.json
        if (!File::exists(base_path('package.json'))) {
            File::put(base_path('package.json'), $this->getPackageJsonContent());
        }
    }

    protected function getRouterContent()
    {
        return <<<'TS'
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
]

export default createRouter({
    history: createWebHistory(),
    routes
})
TS;
    }

    protected function getPackageJsonContent()
    {
        return json_encode([
            'dependencies' => [
                'vue' => '^3.0.0',
                'vue-router' => '^4.0.0',
                'element-plus' => '^2.0.0',
                'tailwindcss' => '^3.0.0',
                'axios' => '^1.0.0'
            ]
        ], JSON_PRETTY_PRINT);
    }

    private function copyStubs()
    {
        // Get the actual package stubs directory
        $packageStubsPath = realpath(__DIR__ . '/../../src/stubs');
        
        if (!$packageStubsPath) {
            $this->fail('Package stubs directory not found');
        }

        // Create test stubs directory if it doesn't exist
        $testStubsPath = __DIR__ . '/../../stubs';
        if (!File::exists($testStubsPath)) {
            File::makeDirectory($testStubsPath . '/vue', 0777, true);
            File::makeDirectory($testStubsPath . '/laravel', 0777, true);
        }

        // Copy Laravel stubs
        $laravelStubs = ['controller.stub', 'migration.stub', 'model.stub', 'resource.stub'];
        foreach ($laravelStubs as $stub) {
            $sourcePath = $packageStubsPath . '/laravel/' . $stub;
            $targetPath = $testStubsPath . '/laravel/' . $stub;
            
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $targetPath);
            } else {
                $this->fail("Laravel stub not found: {$stub}");
            }
        }

        // Copy Vue stubs
        $vueStubs = [
            'component.stub',
            'Empty.vue',
            'form.stub',
            'FormError.vue',
            'index.stub',
            'InputGroup.vue',
            'page-with-layout.stub',
            'page.stub'
        ];
        
        foreach ($vueStubs as $stub) {
            $sourcePath = $packageStubsPath . '/vue/' . $stub;
            $targetPath = $testStubsPath . '/vue/' . $stub;
            
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $targetPath);
            } else {
                $this->fail("Vue stub not found: {$stub}");
            }
        }
    }

    private function getMigrationFile($name)
    {
        $files = glob(database_path('migrations/*_' . $name . '.php'));
        return !empty($files) ? $files[0] : null;
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $directories = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Http/Resources'),
            resource_path('js'),
            database_path('migrations'),
            base_path('routes'),
            __DIR__ . '/../../stubs'
        ];

        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                File::deleteDirectory($directory);
            }
        }

        // Remove package.json
        if (File::exists(base_path('package.json'))) {
            File::delete(base_path('package.json'));
        }

        parent::tearDown();
    }
} 