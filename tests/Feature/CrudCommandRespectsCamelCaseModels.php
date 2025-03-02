<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Shahnewaz\RedprintNg\Commands\MakeCrudCommand;
use Shahnewaz\RedprintNg\Services\StubService;

class CrudCommandRespectsCamelCaseModels extends TestCase
{
    protected $testFilesPath;
    protected $stubService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temp directory if it doesn't exist
        $tempPath = __DIR__ . '/../../temp_files';
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        // Create mock package.json
        $packageJson = json_encode([
            "dependencies" => [
                "vue" => "^3.0.0",
                "element-plus" => "^2.0.0",
                "tailwindcss" => "^3.0.0",
                "axios" => "^1.0.0",
                "vue-router" => "^4.0.0",
                "vue-i18n" => "^9.0.0",
                "lodash" => "^4.17.21"
            ]
        ], JSON_PRETTY_PRINT);
        
        file_put_contents($tempPath . '/package.json', $packageJson);

        $this->testFilesPath = $tempPath;

        $this->stubService = new StubService();

        // Create mock routes.ts file from stub
        $routesStub = $this->stubService->getStub('vue/routes.stub');
        $routesPath = $this->testFilesPath . '/resources/js/router/routes.ts';
        
        // Ensure the directory exists
        if (!file_exists(dirname($routesPath))) {
            mkdir(dirname($routesPath), recursive: true);
        }
        
        File::put($routesPath, $routesStub);
    }

    public function test_it_can_generate_crud()
    {
        $this->withoutMockingConsoleOutput();

        $relationshipData = [
            'endpoint' => 'backend/categories/list',
            'labelColumn' => 'name',
            'relatedModelLower' => 'categories'
        ];

        $relationshipData2 = [
            'endpoint' => 'backend/ingredients/list',
            'labelColumn' => 'title',
            'relatedModelLower' => 'ingredients'
        ];

        // Define columns
        $columns = [
            [
                'name' => 'title',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
            ],
            [
                'name' => 'category_id',
                'type' => 'bigInteger',
                'nullable' => false,
                'default' => null,
                'relationshipData' => $relationshipData
            ],
            [
                'name' => 'ingredient_id',
                'type' => 'bigInteger',
                'nullable' => false,
                'default' => null,
                'relationshipData' => $relationshipData2
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

        // Create model data with all required fields
        $modelData = [
            'model' => 'BlogPost',
            'namespace' => 'Backend',
            'routePrefix' => 'v1',
            'softDeletes' => true,
            'layout' => 'DefaultLayout',
            'columns' => $columns,
            'basePath' => $this->testFilesPath,
            'axios_instance' => config('redprint.axios_instance') ?? null
        ];

        // Create a mock of the command that will return our model data
        $command = $this->getMockBuilder(MakeCrudCommand::class)
            ->onlyMethods(['getModelData'])
            ->setConstructorArgs([$this->testFilesPath])
            ->getMock();
        
        // Set the modelData property
        $command->modelData = $modelData;
        
        $command->expects($this->once())
            ->method('getModelData')
            ->willReturn($modelData);

        // Bind the mock to the container
        $this->app->instance(MakeCrudCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:crud');

        // Get the output for debugging
        $output = Artisan::output();
        fwrite(STDERR, "\nCommand Output:\n" . $output);

        $this->assertEquals(0, $exitCode, "Command failed with output: $output");

        // Assert files were created
        $this->assertFileExists($this->testFilesPath . '/app/Models/BlogPost.php', 'Model file was not created');
        $this->assertFileExists($this->testFilesPath . '/app/Http/Controllers/Api/Backend/BlogPostController.php', 'Controller file was not created');
        $this->assertFileExists($this->testFilesPath . '/app/Http/Resources/BlogPostResource.php', 'Resource file was not created');
        $this->assertFileExists($this->testFilesPath . '/resources/js/components/Backend/BlogPost/BlogPostIndex.vue', 'Index component was not created');
        $this->assertFileExists($this->testFilesPath . '/resources/js/components/Backend/BlogPost/BlogPostForm.vue', 'Form component was not created');
    }
} 