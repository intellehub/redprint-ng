<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeVueCommand extends Command
{
    protected $signature = 'redprint:vue {component}';
    protected $description = 'Create a new Vue component';

    public function handle()
    {
        $component = $this->argument('component');
        $parts = explode('.', $component);
        
        // Remove 'resources.js' from the beginning if present
        if ($parts[0] === 'resources' && $parts[1] === 'js') {
            array_shift($parts);
            array_shift($parts);
        }

        // Get the component name (last part)
        $componentName = array_pop($parts);
        
        // Build the path
        $path = resource_path('js/' . implode('/', $parts));
        
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/vue/component.stub');
        $stub = str_replace('{{componentName}}', $componentName, $stub);

        file_put_contents("{$path}/{$componentName}.vue", $stub);

        $this->info("Vue component {$componentName} created successfully.");
    }
}
