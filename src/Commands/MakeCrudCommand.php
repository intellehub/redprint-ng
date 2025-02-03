<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shahnewaz\RedprintNg\Services\FileService;
use Shahnewaz\RedprintNg\Generators\LaravelGenerator;
use Shahnewaz\RedprintNg\Generators\VueGenerator;
use Symfony\Component\Console\Output\NullOutput;

class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redprint:crud {model} 
        {--namespace=App} 
        {--route-prefix=api/v1}
        {--layout=DefaultLayout}
        {--soft-deletes=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CRUD files for a model';

    private $supportedDataTypes = [
        'string',
        'text',
        'integer',
        'bigInteger',
        'float',
        'double',
        'decimal',
        'boolean',
        'date',
        'dateTime',
        'time',
        'json',
        'enum'
    ];

    private $columns = [];
    protected string $basePath;
    private $isInteractive = true;
    private array $requiredPackages = [
        'vue',
        'element-plus',
        'tailwindcss',
        'axios',
        'vue-router',
        'vue-i18n',
        'lodash',
        '@vueup/vue-quill'
    ];

    public function __construct($basePath = null)
    {
        parent::__construct();
        $this->basePath = $basePath;
        if ($basePath == null) {
            $this->basePath = base_path();
        }
        $this->output = new NullOutput();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $modelData = $this->getModelData();

            if (empty($modelData['columns'])) {
                throw new \InvalidArgumentException('No columns defined in modelData');
            }

            $basePath = $modelData['basePath'] ?? base_path();
            $this->basePath = $basePath;

            $this->createDirectoryStructure();
            $this->validateRequirements();
            
            // Generate Laravel files
            $laravelGenerator = new LaravelGenerator($this->basePath, $modelData, $this);
            $this->generateLaravelFiles($laravelGenerator);

            // Generate Vue files
            $vueGenerator = new VueGenerator($this->basePath, $modelData, $this);
            $this->generateVueFiles($vueGenerator);

            $this->info('CRUD generation completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function validateRequirements(): void
    {
        if (!$this->argument('model')) {
            throw new \RuntimeException('Model name is required');
        }

        $this->validatePackageJson();
    }

    private function validatePackageJson(): void
    {
        $packageJsonPath = "{$this->basePath}/package.json";
        if (!file_exists($packageJsonPath)) {
            throw new \RuntimeException('package.json not found in '.$packageJsonPath);
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $dependencies = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? []
        );

        foreach ($this->requiredPackages as $package) {
            if (!isset($dependencies[$package])) {
                throw new \RuntimeException("Required package {$package} not found in package.json");
            }
        }
    }

    private function createDirectoryStructure(): void
    {
        $fileService = new FileService();
        $directories = [
            'routes',
            'resources/js/components',
            'resources/js/components/Common',
            "resources/js/components/{$this->argument('model')}",
            'resources/js/pages',
        ];

        foreach ($directories as $directory) {
            $fileService->ensureDirectoryExists("{$this->basePath}/{$directory}");
        }
    }

    public function getModelData(): array
    {
        return [
            'model' => $this->argument('model'),
            'namespace' => $this->option('namespace'),
            'routePrefix' => $this->option('route-prefix') ?? config('redprint.route_prefix', 'api/v1'),
            'softDeletes' => $this->option('soft-deletes') ?? true,
            'layout' => $this->option('layout') ?? 'DefaultLayout',
            'columns' => [],
            'basePath' => $this->basePath,
            'axios_instance' => config('redprint.axios_instance')
        ];
    }

    private function generateLaravelFiles(LaravelGenerator $generator): void
    {
        $this->info('Generating Laravel files...');
        $generator->generate();
    }

    private function generateVueFiles(VueGenerator $generator): void
    {
        $this->info('Generating Vue files...');
        $generator->generate();
    }

    private function promptForColumns()
    {
        $columnCount = (int) $this->ask('How many columns do you want to add?');
        $columns = [];

        for ($i = 0; $i < $columnCount; $i++) {
            $this->info("\nColumn " . ($i + 1) . " details:");
            
            $name = $this->ask('Column Name');
            
            $type = $this->choice(
                'Data Type',
                $this->supportedDataTypes,
                0
            );

            // Additional prompts for enum type
            $enumValues = [];
            if ($type === 'enum') {
                $enumValuesStr = $this->ask('Enter enum values (comma-separated)');
                $enumValues = array_map('trim', explode(',', $enumValuesStr));
            }

            $nullable = $this->confirm('Is Nullable?', false);
            
            $default = $this->ask('Default value (press enter to skip)', null);

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => $nullable,
                'default' => $default,
                'enumValues' => $enumValues
            ];
        }

        return $columns;
    }

    protected function getColumns(): array
    {
        // If columns were set via setColumns(), return those
        if (!empty($this->columns) && !$this->isInteractive) {
            return $this->columns;
        }

        // Get them interactively
        $columns = $this->promptForColumns();

        return $columns;
    }
}
