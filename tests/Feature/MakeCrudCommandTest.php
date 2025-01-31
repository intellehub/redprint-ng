<?php

namespace Shahnewaz\RedprintNg\Tests\Feature;

use Shahnewaz\RedprintNg\Tests\TestCase;

class MakeCrudCommandTest extends TestCase
{
    public function test_it_can_generate_crud()
    {
        $this->artisan('redprint:crud', [
            '--model' => 'Post',
            '--namespace' => 'Blog',
            '--route-prefix' => 'blog',
            '--soft-deletes' => 'true',
        ])->assertSuccessful();

        // Assert files were created
        $this->assertFileExists(app_path('Models/Post.php'));
        $this->assertFileExists(app_path('Http/Controllers/Blog/PostController.php'));
        // Add more assertions
    }
} 