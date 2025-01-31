<?php

namespace Shahnewaz\RedprintNg\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shahnewaz\RedprintNg\RedprintServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            RedprintServiceProvider::class,
        ];
    }
} 