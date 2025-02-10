<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Shahnewaz\RedprintNg\Commands\MakeVueCommand;
use Shahnewaz\RedprintNg\Services\StubService;

class MakeVueCommandTest extends TestCase
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

        $this->testFilesPath = $tempPath;
        $this->stubService = new StubService();

        // Ensure the views directory exists
        if (!file_exists($this->testFilesPath . '/resources/js/components/views')) {
            mkdir($this->testFilesPath . '/resources/js/components/views', 0777, true);
        }
    }

    public function test_it_can_generate_blank_component()
    {
        $this->withoutMockingConsoleOutput();

        // Create model data with all required fields
        $modelData = [
            'basePath' => $this->testFilesPath,
            'axios_instance' => config('redprint.axios_instance')
        ];

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask'])
            ->setConstructorArgs([$this->testFilesPath])
            ->getMock();
        
        // Set the modelData property
        $command->modelData = $modelData;

        $command->expects($this->once())
            ->method('choice')
            ->willReturn('blank');

        $command->expects($this->once())
            ->method('ask')
            ->willReturn('@/components/views/BlankTest.vue');

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');

        // Get the output for debugging
        $output = Artisan::output();
        fwrite(STDERR, "\nCommand Output:\n" . $output);

        $this->assertEquals(0, $exitCode, "Command failed with output: $output");

        // Assert file was created
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/BlankTest.vue', 
            'Blank component was not created'
        );
    }

    public function test_it_can_generate_list_component()
    {
        $this->withoutMockingConsoleOutput();

        // Define columns for the list component
        $columns = [
            [
                'name' => 'title',
                'type' => 'string',
                'nullable' => false,
            ],
            [
                'name' => 'status',
                'type' => 'boolean',
                'nullable' => false,
            ]
        ];

        // Create model data with all required fields
        $modelData = [
            'basePath' => $this->testFilesPath,
            'axios_instance' => config('redprint.axios_instance'),
            'columns' => $columns
        ];

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask', 'promptForColumns'])
            ->setConstructorArgs([$this->testFilesPath])
            ->getMock();
        
        // Set the modelData property
        $command->modelData = $modelData;

        $command->expects($this->once())
            ->method('choice')
            ->willReturn('list');

        $command->expects($this->exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(
                'api/v1/items',
                '@/components/views/ItemList.vue'
            );

        $command->expects($this->once())
            ->method('promptForColumns')
            ->willReturn($columns);

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/ItemList.vue',
            'List component was not created'
        );
    }

    public function test_it_can_generate_form_component()
    {
        $this->withoutMockingConsoleOutput();

        // Define columns for the form component with relationships
        $columns = [
            [
                'name' => 'title',
                'type' => 'string',
                'nullable' => false,
            ],
            [
                'name' => 'category_id',
                'type' => 'bigInteger',
                'nullable' => false,
                'relationshipData' => [
                    'endpoint' => 'api/v1/categories/list',
                    'labelColumn' => 'name',
                    'relatedModelLower' => 'categories'
                ]
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'nullable' => false,
            ],
            [
                'name' => 'published',
                'type' => 'boolean',
                'nullable' => false,
            ]
        ];

        // Create model data with all required fields
        $modelData = [
            'basePath' => $this->testFilesPath,
            'axios_instance' => config('redprint.axios_instance'),
            'columns' => $columns
        ];

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask', 'promptForColumns'])
            ->setConstructorArgs([$this->testFilesPath])
            ->getMock();
        
        // Set the modelData property
        $command->modelData = $modelData;

        $command->expects($this->once())
            ->method('choice')
            ->willReturn('form');

        $command->expects($this->exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(
                'api/v1/items',
                '@/components/views/ItemForm.vue'
            );

        $command->expects($this->once())
            ->method('promptForColumns')
            ->willReturn($columns);

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');

        $this->assertEquals(0, $exitCode);
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/ItemForm.vue',
            'Form component was not created'
        );
    }
}
