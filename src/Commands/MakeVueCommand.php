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
        $type = $this->choice(
            'What type of component would you like to create?',
            ['blank', 'list', 'form']
        );

        switch ($type) {
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
        return 0;
    }

    private function handleBlankTemplate(): void
    {
        $path = $this->askForComponentPath();
        $componentName = basename($path, '.vue');
        
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
        $columns = $this->promptForColumns(null, false, false, false);
        $path = $this->askForComponentPath();
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
        $columns = $this->promptForColumns(null, true, false, true);
        $path = $this->askForComponentPath();
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

    public function promptForColumns(?string $namespace = null, $typePrompt = true, $detailsPrompt = true, $relationsPrompt = true): array
    {
        return $this->getColumnInput($namespace, $typePrompt, $detailsPrompt, $relationsPrompt);
    }

    private function askForComponentPath(): string
    {
        $hint = "Note: '@/components' translates to 'resources/js/components'";
        $this->info($hint);
        
        while (true) {
            $path = $this->ask('Please enter the component path (e.g., @/components/Admin/UserProfile.vue):');
            
            // Check if path ends with .vue
            if (!str_ends_with($path, '.vue')) {
                $this->error('Component path must end with .vue extension');
                continue;
            }

            // Get the filename without extension
            $filename = basename($path, '.vue');
            
            // Validate filename (must be PascalCase, letters only)
            if (!preg_match('/^[A-Z][a-zA-Z]*$/', $filename)) {
                $this->error('Component filename must start with a capital letter and contain only letters (PascalCase)');
                continue;
            }

            // Validate path segments
            $pathWithoutFile = dirname($path);
            if (!preg_match('/^[@\/a-zA-Z]+$/', $pathWithoutFile)) {
                $this->error('Path segments must contain only letters and forward slashes');
                continue;
            }

            return $path;
        }
    }
}
