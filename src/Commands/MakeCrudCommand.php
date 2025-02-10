<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shahnewaz\RedprintNg\Services\FileService;
use Shahnewaz\RedprintNg\Generators\LaravelGenerator;
use Shahnewaz\RedprintNg\Generators\VueGenerator;
use Symfony\Component\Console\Output\NullOutput;
use Illuminate\Support\Str;
use Shahnewaz\RedprintNg\Enums\DataTypes;
use Shahnewaz\RedprintNg\Traits\HandlesColumnInput;

class MakeCrudCommand extends Command
{
    use HandlesColumnInput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redprint:crud';

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
        $this->basePath = $basePath == null ? base_path() : $basePath;
        $this->output = new NullOutput();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $modelData = $this->getModelData();

            if (empty($modelData['model'])) {
                throw new \InvalidArgumentException('Model name is required.');
            }

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
        ];

        foreach ($directories as $directory) {
            $fileService->ensureDirectoryExists("{$this->basePath}/{$directory}");
        }
    }

    public function getModelData(): array
    {
        // Get model name with validation
        do {
            $model = $this->ask('Please specify the Model name (Should be singular and title case)');
            
            if (empty($model)) {
                $this->error('Model name cannot be empty.');
                continue;
            }
            
            if (!preg_match('/^[A-Z][a-zA-Z]*$/', $model)) {
                $this->error('Model name must start with a capital letter and contain only letters.');
                continue;
            }
            
            break;
        } while (true);
        
        $this->info('Model name: ' . $model);

        // Get namespace with validation
        do {
            $namespace = $this->ask('Please specify namespace.');
            
            if (empty($namespace)) {
                $this->error('Namespace cannot be empty.');
                continue;
            }
            
            if (strtolower($namespace) === 'api') {
                $this->error('Namespace must not be "Api".');
                continue;
            }
            
            if (!preg_match('/^[A-Z][a-zA-Z]*$/', $namespace)) {
                $this->error('Namespace must start with a capital letter and contain only letters.');
                continue;
            }
            
            $namespace = trim($namespace, '\\');
            break;
        } while (true);

        $this->info('Namespace: ' . $namespace);

        // Get route prefix
        $routePrefix = $this->ask('Please specify route prefix. Defaults to: v1', 'v1');
        $this->info('Route prefix: ' . $routePrefix);

        // Get layout
        $layout = $this->ask('Please specify the Vue component layout. Defaults to: DefaultLayout', 'DefaultLayout');
        $this->info('Layout: ' . $layout);

        return [
            'model' => $model,
            'namespace' => $namespace,
            'routePrefix' => $routePrefix,
            'layout' => $layout,
            'softDeletes' => $this->promptForSoftDeletes(),
            'columns' => $this->getColumns($namespace),
            'basePath' => $this->basePath,
            'axios_instance' => config('redprint.axios_instance')
        ];
    }

    public function askYesNo(string $question, string $default = 'y'): bool
    {
        do {
            $response = strtolower($this->ask($question . ' (y/n) [' . $default . ']', $default));
            
            if (!in_array($response, ['y', 'n'])) {
                $this->error('Invalid option. Please answer with y or n.');
                continue;
            }
            $this->info('You selected: ' . $response);
            return $response === 'y';
        } while (true);
    }

    private function promptForSoftDeletes(): bool
    {
        return $this->askYesNo('Do you want to enable soft deletes?');
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

    protected function getColumns($namespace): array
    {
        // If columns were set via setColumns(), return those
        if (!empty($this->columns) && !$this->isInteractive) {
            return $this->columns;
        }

        // Get them interactively
        $columns = $this->promptForColumns($namespace);

        return $columns;
    }

    private function getColumnType(): string
    {
        return $this->choice(
            'What is the column type?',
            DataTypes::getAvailableTypes(),
            0
        );
    }
}
