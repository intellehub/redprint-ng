<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Shahnewaz\RedprintNg\Generators\VueGenerator;
use Shahnewaz\RedprintNg\Traits\HandlesColumnInput;

class MakeVueCommand extends Command
{
    use HandlesColumnInput;

    protected $signature = 'redprint:vue';
    protected $description = 'Create a new Vue component';
    
    private VueGenerator $generator;
    
    public function __construct()
    {
        parent::__construct();
        $this->generator = new VueGenerator($this);
    }

    public function handle()
    {
        $template = $this->choice(
            'Please choose the template:',
            [
                'blank' => 'Blank (Default)',
                'list' => 'List Page',
                'form' => 'Form Page'
            ],
            'blank'
        );

        switch ($template) {
            case 'blank':
                $this->handleBlankTemplate();
                break;
            case 'list':
                $this->handleListTemplate();
                break;
            case 'form':
                $this->handleFormTemplate();
                break;
        }
    }

    private function handleBlankTemplate(): void
    {
        $path = $this->ask('Please enter the component path (e.g., @/components/views/MyFile.vue):');
        $normalizedPath = $this->generator->normalizePath($path);
        
        $componentName = basename($normalizedPath, '.vue');
        $this->generator->generateBlankComponent($normalizedPath, $componentName);
    }

    private function handleListTemplate(): void
    {
        $endpoint = $this->ask('Please input the API endpoint to fetch data from:');
        $columns = $this->promptForColumns();
        $path = $this->ask('Please enter the component path:');
        
        $this->generator->setModelData([
            'columns' => $columns,
            'endpoint' => $endpoint
        ]);
        
        $this->generator->generateListPageComponent($this->generator->normalizePath($path));
    }

    private function handleFormTemplate(): void
    {
        $endpoint = $this->ask('Please input the API endpoint to submit the form:');
        $columns = $this->promptForColumns();
        $path = $this->ask('Please enter the component path:');
        
        $this->generator->setModelData([
            'columns' => $columns,
            'endpoint' => $endpoint
        ]);
        
        $this->generator->generateFormPageComponent($this->generator->normalizePath($path));
    }
}
