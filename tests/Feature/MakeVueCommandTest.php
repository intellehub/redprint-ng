<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Shahnewaz\RedprintNg\Commands\MakeVueCommand;

class MakeVueCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_can_generate_blank_component()
    {
        $this->withoutMockingConsoleOutput();

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask'])
            ->getMock();

        // Set up the expected interaction sequence
        $command->expects($this->once())
            ->method('choice')
            ->with(
                'Please choose the template:',
                [
                    'blank' => 'Blank (Default)',
                    'list' => 'List Page',
                    'form' => 'Form Page'
                ],
                'blank'
            )
            ->willReturn('blank');

        $command->expects($this->once())
            ->method('ask')
            ->with('Please enter the component path (e.g., @/components/views/MyFile.vue):')
            ->willReturn('@/components/views/BlankTest.vue');

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');
        
        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert file was created
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/BlankTest.vue',
            'Blank component was not created'
        );

        // Assert file content
        $content = File::get($this->testFilesPath . '/resources/js/components/views/BlankTest.vue');
        $this->assertStringContainsString('name: \'BlankTest\'', $content);
    }

    public function test_it_can_generate_list_component()
    {
        $this->withoutMockingConsoleOutput();

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask', 'promptForColumns'])
            ->getMock();

        // Define test columns
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

        // Set up the expected interaction sequence
        $command->expects($this->once())
            ->method('choice')
            ->willReturn('list');

        $command->expects($this->exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(
                'api/v1/items', // API endpoint
                '@/components/views/ItemList.vue' // Component path
            );

        $command->expects($this->once())
            ->method('promptForColumns')
            ->willReturn($columns);

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');
        
        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert file was created
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/ItemList.vue',
            'List component was not created'
        );

        // Assert file content
        $content = File::get($this->testFilesPath . '/resources/js/components/views/ItemList.vue');
        $this->assertStringContainsString('api/v1/items', $content);
        $this->assertStringContainsString('title', $content);
        $this->assertStringContainsString('status', $content);
    }

    public function test_it_can_generate_form_component()
    {
        $this->withoutMockingConsoleOutput();

        // Create a mock of the command
        $command = $this->getMockBuilder(MakeVueCommand::class)
            ->onlyMethods(['choice', 'ask', 'promptForColumns'])
            ->getMock();

        // Define test columns with a relationship
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
                    'endpoint' => 'api/v1/categories',
                    'labelColumn' => 'name',
                    'relatedModel' => 'Category',
                    'relatedModelLower' => 'category'
                ]
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'nullable' => true,
            ]
        ];

        // Set up the expected interaction sequence
        $command->expects($this->once())
            ->method('choice')
            ->willReturn('form');

        $command->expects($this->exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(
                'api/v1/items', // API endpoint
                '@/components/views/ItemForm.vue' // Component path
            );

        $command->expects($this->once())
            ->method('promptForColumns')
            ->willReturn($columns);

        // Bind the mock to the container
        $this->app->instance(MakeVueCommand::class, $command);

        // Run the command
        $exitCode = Artisan::call('redprint:vue');
        
        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert file was created
        $this->assertFileExists(
            $this->testFilesPath . '/resources/js/components/views/ItemForm.vue',
            'Form component was not created'
        );

        // Assert file content
        $content = File::get($this->testFilesPath . '/resources/js/components/views/ItemForm.vue');
        $this->assertStringContainsString('api/v1/items', $content);
        $this->assertStringContainsString('title', $content);
        $this->assertStringContainsString('content', $content);
        $this->assertStringContainsString('category_id', $content);
        $this->assertStringContainsString('categoryData', $content);
        $this->assertStringContainsString('fetchCategoryData', $content);
    }
} 