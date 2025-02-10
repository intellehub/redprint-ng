<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Shahnewaz\RedprintNg\Generators\VueGenerator;
use Shahnewaz\RedprintNg\Traits\HandlesColumnInput;
use Symfony\Component\Console\Output\NullOutput;

class MakeVueCommand extends Command
{
    use HandlesColumnInput;

    protected $signature = 'redprint:vue';
    protected $description = 'Create a new Vue component';
    
    private ?VueGenerator $generator = null;
    public array $modelData;
    protected string $basePath;

    public function __construct($basePath = null)
    {
        parent::__construct();
        $this->basePath = $basePath ?? base_path();
        $this->output = new NullOutput();
        $this->modelData = [
            'axios_instance' => config('redprint.axios_instance'),
            'basePath' => $this->basePath,
        ];
    }

    protected function getGenerator(): VueGenerator
    {
        if (!$this->generator) {
            $this->generator = new VueGenerator($this->modelData['basePath'], $this->modelData, $this);
        }
        return $this->generator;
    }

    public function handle()
    {
        error_log("Starting handle() method");
        
        $type = $this->choice(
            'What type of component would you like to create?',
            ['blank', 'list', 'form']
        );
        
        error_log("Selected type: " . $type);

        switch ($type) {
            case 'blank':
                error_log("Handling blank template");
                $this->handleBlankTemplate();
                break;
            case 'list':
                error_log("Handling list template");
                $this->handleListTemplate();
                break;
            case 'form':
                error_log("Handling form template");
                $this->handleFormTemplate();
                break;
        }

        error_log("Finished handle() method");
        return 0;
    }

    private function handleBlankTemplate(): void
    {
        $path = $this->ask('Please enter the component path:');
        $componentName = basename($path, '.vue');
        
        // Set minimal model data required for blank component
        $this->getGenerator()->setModelData([
            'model' => $componentName,
            'componentName' => $componentName,
            'namespace' => 'Views',
            'axios_instance' => config('redprint.axios_instance'),
            'basePath' => $this->basePath,
        ]);
        
        $this->getGenerator()->generateBlankComponent($this->getGenerator()->normalizePath($path), $componentName);
    }

    private function handleListTemplate(): void
    {
        $endpoint = $this->ask('Please input the API endpoint to fetch data from:');
        $columns = $this->promptForColumns();
        $path = $this->ask('Please enter the component path:');
        $componentName = basename($path, '.vue');
        $searchColumn = $columns[0]['name'];
        
        $this->getGenerator()->setModelData([
            'componentName' => $componentName,
            'columns' => $columns,
            'endpoint' => $endpoint,
            'searchColumn' => $searchColumn,
            'axios_instance' => config('redprint.axios_instance'),
            'basePath' => $this->basePath,
        ]);
        
        $this->getGenerator()->generateListPageComponent($this->getGenerator()->normalizePath($path));
    }

    private function handleFormTemplate(): void
    {
        $endpoint = $this->ask('Please input the API endpoint to submit the form:');
        $columns = $this->promptForColumns();
        $path = $this->ask('Please enter the component path:');
        $componentName = basename($path, '.vue');

        $this->getGenerator()->setModelData([
            'componentName' => $componentName,
            'columns' => $columns,
            'endpoint' => $endpoint,
            'axios_instance' => config('redprint.axios_instance'),
            'basePath' => $this->basePath,
        ]);
        
        $this->getGenerator()->generateFormPageComponent($this->getGenerator()->normalizePath($path));
    }

    public function promptForColumns() {
        return $this->getColumnInput();
    }
}
