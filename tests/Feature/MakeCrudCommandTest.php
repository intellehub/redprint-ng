<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeCrudCommandTest extends TestCase
{
    protected $testFilesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFilesPath = __DIR__ . '/../../temp_files';
    }

    public function test_it_can_generate_crud()
    {
        $this->withoutMockingConsoleOutput();

        // Define columns
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

        // Get a fresh instance of the command
        $command = new \Shahnewaz\RedprintNg\Commands\MakeCrudCommand();
        $command->setColumns($columns);
        $command->setBasePath($this->testFilesPath);

        // Register the command instance
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($command);

        // Run the command
        $exitCode = Artisan::call('redprint:crud', [
            '--model' => 'Post',
            '--namespace' => 'Blog',
            '--route-prefix' => 'blog',
            '--soft-deletes' => 'true',
        ]);

        // Get the output for debugging
        $output = Artisan::output();
        fwrite(STDERR, "\nCommand Output:\n" . $output);

        $this->assertEquals(0, $exitCode, "Command failed with output: $output");

        // Assert files were created
        $this->assertFileExists($this->testFilesPath . '/app/Models/Post.php', 'Model file was not created');
        $this->assertFileExists($this->testFilesPath . '/app/Http/Controllers/Blog/PostController.php', 'Controller file was not created');
        $this->assertFileExists($this->testFilesPath . '/app/Http/Resources/PostResource.php', 'Resource file was not created');
        $this->assertFileExists($this->testFilesPath . '/resources/js/pages/Post.vue', 'Page component was not created');
        $this->assertFileExists($this->testFilesPath . '/resources/js/components/Post/Index.vue', 'Index component was not created');
        $this->assertFileExists($this->testFilesPath . '/resources/js/components/Post/Form.vue', 'Form component was not created');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
} 