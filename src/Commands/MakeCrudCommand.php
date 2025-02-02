<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Facades\File;
use Shahnewaz\RedprintNg\Services\FileService;
use Shahnewaz\RedprintNg\Generators\LaravelGenerator;
use Shahnewaz\RedprintNg\Generators\VueGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;

class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redprint:crud 
        {model : The name of the model}
        {--namespace= : The namespace for the generated files}
        {--route-prefix= : The route prefix for API endpoints}
        {--soft-deletes=false : Whether to include soft deletes}
        {--layout= : The layout component to wrap the page with}
        {--base-path= : The base path for file generation}';

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
        'lodash'
    ];

    private array $modelData = [];

    public function __construct()
    {
        parent::__construct();
        $this->basePath = $basePath;
        $this->output = new NullOutput();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->validateRequirements();
            $this->createDirectoryStructure();
            
            $modelData = $this->getModelData();
            
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
            'softDeletes' => $this->option('soft-deletes'),
            'layout' => $this->option('layout'),
            'columns' => config('redprint.columns', []),
            'basePath' => $this->basePath,
            'axios_instance' => config('redprint.axios_instance')
        ];
    }

    private function generateLaravelFiles(LaravelGenerator $generator): void
    {
        $this->info('Generating Laravel files...');
        $generator->generateMigration();
        $generator->generateModel();
        $generator->generateController();
        $generator->generateResource();
        $generator->updateRoutes();
    }

    private function generateVueFiles(VueGenerator $generator): void
    {
        $this->info('Generating Vue files...');
        $generator->generateCommonComponents();
        $generator->generateIndexComponent();
        $generator->generateFormComponent();
        $generator->generatePageComponent();
        $generator->updateRouter();
    }

    private function checkDependencies()
    {
        $packageJson = $this->basePath . '/package.json';
        if (!file_exists($packageJson)) {
            throw new \Exception('package.json not found. Make sure you are in a Laravel project with Vue.js setup.');
        }

        $packages = json_decode(file_get_contents($packageJson), true);
        $dependencies = array_merge(
            $packages['dependencies'] ?? [],
            $packages['devDependencies'] ?? []
        );

        $required = [
            'tailwindcss' => 'Tailwind CSS',
            'element-plus' => 'Element Plus',
            'axios' => 'Axios',
            'vue' => 'Vue.js',
            'vue-router' => 'Vue Router',
            'vue-i18n' => 'Vue I18n',
            'lodash' => 'Lodash',
        ];

        $missing = [];
        foreach ($required as $package => $name) {
            if (!isset($dependencies[$package])) {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Missing required packages: ' . implode(', ', $missing));
        }
    }

    private function copyCommonComponents()
    {
        $commonPath = $this->basePath . '/resources/js/components/Common';
        if (!file_exists($commonPath)) {
            mkdir($commonPath, 0777, true);
        }

        $components = ['FormError', 'InputGroup', 'Empty'];
        foreach ($components as $component) {
            $targetPath = "{$commonPath}/{$component}.vue";
            if (file_exists($targetPath)) {
                $this->warn("{$component} component already exists. Skipping...");
                continue;
            }

            copy(
                __DIR__ . "/../stubs/vue/{$component}.vue",
                $targetPath
            );
            $this->info("Common component {$component} created successfully.");
        }
    }

    private function generateModel($model, $softDeletes)
    {
        $filePath = $this->basePath . '/app/Models/' . $model . '.php';
        if (file_exists($filePath)) {
            $this->warn("Model {$model} already exists. Skipping...");
            return;
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/model.stub');
        
        $stub = str_replace(
            ['{{modelName}}', '{{softDeletes}}', '{{useSoftDeletes}}'],
            [
                $model,
                $softDeletes ? "use Illuminate\Database\Eloquent\SoftDeletes;" : "",
                $softDeletes ? "use SoftDeletes;" : ""
            ],
            $stub
        );

        file_put_contents($filePath, $stub);
        $this->info("Model {$model} created successfully.");
    }

    private function generateController($model, $namespace)
    {
        $path = $this->basePath . "/app/Http/Controllers/" . ($namespace ? "{$namespace}/" : "");
        $filePath = "{$path}{$model}Controller.php";

        if (file_exists($filePath)) {
            $this->warn("Controller {$model}Controller already exists. Skipping...");
            return;
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/controller.stub');

        $controllerNamespace = $namespace
            ? "App\\Http\\Controllers\\{$namespace}"
            : "App\\Http\\Controllers";

        $softDeleteMethods = $this->option('soft-deletes') === 'true'
            ? $this->getSoftDeleteMethods($model)
            : '';

        $stub = str_replace(
            [
                '{{namespace}}',
                '{{modelName}}',
                '{{softDeleteMethods}}'
            ],
            [
                $controllerNamespace,
                $model,
                $softDeleteMethods
            ],
            $stub
        );

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($filePath, $stub);
        $this->info("Controller {$model}Controller created successfully.");
    }

    private function getFirstColumn($model)
    {
        $columns = $this->columns;
        if (!empty($columns)) {
            return $columns[0]['name'];
        }
        throw new \Exception("No columns defined for the model.");
    }

    private function generateResource($model)
    {
        $path = $this->basePath . '/app/Http/Resources/';
        $filePath = "{$path}{$model}Resource.php";

        if (file_exists($filePath)) {
            $this->warn("Resource {$model}Resource already exists. Skipping...");
            return;
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/resource.stub');
        
        $columnDefinitions = $this->generateResourceColumns();
        $softDeletesResource = $this->option('soft-deletes') === 'true' 
            ? "'deleted_at' => \$this->deleted_at," 
            : '';

        $stub = str_replace(
            ['{{modelName}}', '{{columns}}', '{{softDeletesResource}}'],
            [$model, $columnDefinitions, $softDeletesResource],
            $stub
        );

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($filePath, $stub);
        $this->info("Resource {$model}Resource created successfully.");
    }

    private function generateResourceColumns()
    {
        $definitions = [];
        foreach ($this->columns as $column) {
            $definitions[] = "            '{$column['name']}' => \$this->{$column['name']},";
        }
        return implode("\n", $definitions);
    }

    private function promptForColumns()
    {
        // Skip if not in interactive mode
        if (!$this->isInteractive) {
            return $this->columns;
        }

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

    private function generateMigration($model, $columns, $softDeletes)
    {
        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/migration.stub');

        
        $tableName = Str::plural(Str::snake($model));
        $columnDefinitions = $this->generateColumnDefinitions($columns);
        $softDeletesColumn = $softDeletes ? "\$table->softDeletes();" : '';

        $stub = str_replace(
            [
                '{{tableName}}', 
                '{{columnDefinitions}}',
                '{{softDeletesColumn}}'
            ],
            [
                $tableName, 
                $columnDefinitions,
                $softDeletesColumn
            ],
            $stub
        );

        $fileName = date('Y_m_d_His') . "_create_{$tableName}_table.php";
        $filePath = $this->basePath . '/database/migrations/' . $fileName;
        file_put_contents($filePath, $stub);
    }

    private function generateColumnDefinitions($columns)
    {
        $definitions = [];
        
        foreach ($columns as $column) {
            $def = "\$table->{$column['type']}('{$column['name']}')";
            
            if ($column['type'] === 'enum') {
                $enumValues = array_map(function($value) {
                    return "'{$value}'";
                }, $column['enumValues']);
                $def = "\$table->enum('{$column['name']}', [" . implode(', ', $enumValues) . "])";
            }
            
            if ($column['nullable']) {
                $def .= "->nullable()";
            }
            
            if ($column['default'] !== null) {
                $def .= "->default('{$column['default']}')";
            }
            
            $definitions[] = str_pad("            {$def};", 12, " ");
        }
        
        return implode("\n", $definitions);
    }

    private function validateLayout($layout)
    {
        if (!$layout) {
            return;
        }

        $layoutPath = resource_path("js/layouts/{$layout}.vue");
        if (!file_exists($layoutPath)) {
            throw new \Exception("Layout file not found at: {$layoutPath}");
        }
    }

    private function generatePageComponent($model, $layout = null)
    {
        $pagesPath = $this->basePath . '/resources/js/pages';
        $filePath = "{$pagesPath}/{$model}.vue";

        if (file_exists($filePath)) {
            $this->warn("{$model} page component already exists. Skipping...");
            return;
        }

        if (!file_exists($pagesPath)) {
            mkdir($pagesPath, 0777, true);
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/vue/page.stub');

        if ($layout) {
            $stub = file_get_contents(__DIR__ . '/../stubs/vue/page-with-layout.stub');
            $stub = str_replace(['{{layout}}'], [$layout], $stub);
        }

        $stub = str_replace(['{{modelName}}'], [$model], $stub);
        
        file_put_contents($filePath, $stub);
        $this->info("{$model} page component created successfully.");
    }

    private function createVueComponents()
    {
        $model = $this->argument('model');
        
        // Ensure directories exist first
        $this->ensureDirectoriesExist();

        // Debug output
        $this->info("Creating files in: {$this->basePath}");
        $this->info("Current working directory: " . getcwd());

        try {
            // Create Index component
            $indexContent = $this->getStub('vue/index.stub');
            $indexPath = "{$this->basePath}/resources/js/components/{$model}/Index.vue";
            $this->info("Attempting to create file at: {$indexPath}");
            
            if (!is_dir(dirname($indexPath))) {
                $this->info("Directory doesn't exist, creating: " . dirname($indexPath));
                mkdir(dirname($indexPath), 0777, true);
            }
            
            if ($this->createFile($indexPath, $indexContent)) {
                $this->info("{$model} index component created successfully at {$indexPath}");
            } else {
                throw new \RuntimeException("Failed to create index component");
            }

            // Create Form component
            $formContent = $this->getStub('vue/form.stub');
            $formPath = "{$this->basePath}/resources/js/components/{$model}/Form.vue";
            if ($this->createFile($formPath, $formContent)) {
                $this->info("{$model} form component created successfully.");
            } else {
                throw new \RuntimeException("Failed to create form component");
            }

            // Create Page component
            $pageContent = $this->getStub('vue/page.stub');
            $pagePath = "{$this->basePath}/resources/js/pages/{$model}.vue";
            if ($this->createFile($pagePath, $pageContent)) {
                $this->info("{$model} page component created successfully.");
            } else {
                throw new \RuntimeException("Failed to create page component");
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Error creating Vue components: " . $e->getMessage());
            return false;
        }
    }

    private function createFile($path, $content)
    {
        try {
            // Double-check directory exists
            $directory = dirname($path);
            if (!file_exists($directory)) {
                $this->info("Creating directory: {$directory}");
                if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Failed to create directory: {$directory}");
                }
            }

            // Verify directory exists and is writable
            if (!is_dir($directory) || !is_writable($directory)) {
                throw new \RuntimeException("Directory {$directory} does not exist or is not writable");
            }

            // Write file
            $result = file_put_contents($path, $content);
            if ($result === false) {
                throw new \RuntimeException("Failed to write file: {$path}");
            }

            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    private function updateLaravelRoutes($model, $namespace, $routePrefix)
    {
        $routesFile = $this->basePath . '/routes/api.php';
        $content = file_get_contents($routesFile);

        $routeName = Str::plural(Str::snake($model));
        $controllerNamespace = $namespace 
            ? "App\\Http\\Controllers\\{$namespace}\\{$model}Controller" 
            : "App\\Http\\Controllers\\{$model}Controller";

        $routes = "
Route::prefix('" . ($routePrefix ? $routePrefix : '') . "')->middleware(['auth:api'])->group(function () {
    Route::get('{$routeName}', [{$controllerNamespace}::class, 'getIndex'])->name('{$routeName}.index');
    Route::get('{$routeName}/{id}', [{$controllerNamespace}::class, 'show'])->name('{$routeName}.show');
    Route::post('{$routeName}/save', [{$controllerNamespace}::class, 'save'])->name('{$routeName}.save');
    Route::delete('{$routeName}/{id}', [{$controllerNamespace}::class, 'delete'])->name('{$routeName}.delete');";

        if ($this->option('soft-deletes') === 'true') {
            $routes .= "
    Route::delete('{$routeName}/{id}/force', [{$controllerNamespace}::class, 'deleteFromTrash'])->name('{$routeName}.force-delete');";
        }

        $routes .= "
});";

        // Check for fallback route
        if (strpos($content, 'Route::fallback') !== false) {
            // Find the position of the fallback route
            $fallbackPosition = strpos($content, 'Route::fallback');
            
            // Insert new routes before the fallback
            $content = substr_replace($content, $routes . "\n\n", $fallbackPosition, 0);
        } else {
            // If no fallback route, append to the end
            $content .= $routes;
        }

        file_put_contents($routesFile, $content);
        $this->info("API routes for {$model} added successfully.");
    }

    public function setColumns(array $columns): void
    {
        $this->columns = $columns;
        $this->isInteractive = false;
    }

    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }

    private function createDirectories($basePath)
    {
        $directories = [
            $basePath . '/app/Models',
            $basePath . '/app/Http/Controllers/Blog',
            $basePath . '/app/Http/Resources',
            $basePath . '/resources/js/pages',
            $basePath . '/resources/js/components',
            $basePath . '/resources/js/router',
            $basePath . '/database/migrations',
            $basePath . '/routes',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
        }
    }

    protected function createRouteFiles()
    {
        // Use the base path for route files
        $routesPath = $this->basePath . '/routes';
        
        // Debug output to check the base path
        $this->info("Using routes path: {$routesPath}");

        if (!File::exists($routesPath)) {
            File::makeDirectory($routesPath, 0777, true);
        }

        // Create api.php if it doesn't exist
        if (!File::exists($routesPath . '/api.php')) {
            File::put($routesPath . '/api.php', $this->getApiRouteStub());
            $this->info("Created api.php at: {$routesPath}/api.php");
        } else {
            $this->info("api.php already exists at: {$routesPath}/api.php");
        }
    }

    protected function getApiRouteStub()
    {
        return $this->getStub('laravel/api.stub');
    }

    private function generateVueRoutes($model)
    {
        $routerFile = resource_path(config('redprint.vue_router_location'));

        if (!file_exists($routerFile)) {
            throw new \Exception("Vue router file not found at: " . config('redprint.vue_router_location'));
        }

        $content = file_get_contents($routerFile);
        $routeName = Str::plural(Str::snake($model));

        $imports = "import {$model}Page from \"@/pages/{$model}.vue\";
import {$model}Index from \"@/components/{$model}/Index.vue\";
import {$model}Form from \"@/components/{$model}/Form.vue\";
";

        // Find the last import statement
        $lastImportPos = strrpos($content, "import");
        if ($lastImportPos === false) {
            $lastImportPos = 0;
        } else {
            // Find the end of the last import line
            $lastImportPos = strpos($content, "\n", $lastImportPos) + 1;
        }

        // Add new imports after the last import
        $content = substr_replace($content, $imports, $lastImportPos, 0);

        $routes = "    {
            path: '/{$routeName}',
            name: 'pages.{$routeName}',
            component: {$model}Page,
            children: [
                {
                    path: '',
                    name: 'pages.{$routeName}.index',
                    component: {$model}Index,
                    meta: {title: 'routes.titles.{$routeName}', description: 'routes.descriptions.{$routeName}', requiresAuth: true},
                },
                {
                    path: 'edit',
                    name: 'pages.{$routeName}.edit',
                    component: {$model}Form,
                    meta: {title: 'routes.titles.edit_{$model}', description: 'routes.descriptions.edit_{$model}', requiresAuth: true},
                },
                {
                    path: 'new',
                    name: 'pages.{$routeName}.new',
                    component: {$model}Form,
                    meta: {title: 'routes.titles.new_{$model}', description: 'routes.descriptions.new_{$model}', requiresAuth: true},
                },
            ],
        },\n";

        // Find the last route entry
        $lastBracketPos = strrpos($content, "]");
        if ($lastBracketPos !== false) {
            $content = substr_replace($content, $routes, $lastBracketPos - 1, 0);
        }

        file_put_contents($routerFile, $content);
        $this->info("Vue routes for {$model} added successfully.");
    }

    private function generateIndexComponent($model, $columns)
    {
        $indexStub = $this->getStub('vue/index.stub');

        // Generate table headers
        $tableHeaders = '';
        foreach ($columns as $column) {
            $tableHeaders .= "<th scope=\"col\" class=\"py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8\">\$t('common.{$column['name']}')</th>\n";
        }

        // Generate table body rows
        $tableBodyRows = '';
        foreach ($columns as $column) {
            $tableBodyRows .= "<td class=\"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8\">{{ item.{$column['name']} }}</td>\n";
        }

        // Replace placeholders in the stub
        $indexComponent = str_replace('{{tableHeaders}}', $tableHeaders, $indexStub);
        $indexComponent = str_replace('{{tableBodyRows}}', $tableBodyRows, $indexComponent);
        $indexComponent = str_replace('{{modelName}}', $model, $indexComponent);
        $indexComponent = str_replace('{{routePath}}', strtolower($model), $indexComponent);

        // Use the correct base path for file generation
        $indexPath = resource_path("js/components/{$model}/Index.vue");
        
        // Ensure the directory exists
        if (!file_exists(dirname($indexPath))) {
            mkdir(dirname($indexPath), 0777, true);
        }

        File::put($indexPath, $indexComponent);
        $this->info("{$model} index component created successfully.");
    }

    private function getSoftDeleteMethods($model)
    {
        return "
    public function deleteFromTrash(\$id)
    {
        \$model = {$model}::withTrashed()->findOrFail(\$id);
        \$model->forceDelete();
        
        return response()->json(['message' => '{$model} permanently deleted'], 204);
    }

    public function restore(\$id)
    {
        \$model = {$model}::withTrashed()->findOrFail(\$id);
        \$model->restore();
        
        return response()->json(['message' => '{$model} restored successfully']);
    }";
    }


    private function getStub($path)
    {
        $stubPath = __DIR__ . "/../stubs/{$path}";
        
        // Debug the full path
        $this->info("Looking for stub at: {$stubPath}");
        $this->info("__DIR__ is: " . __DIR__);
        
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found at: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $modelData = $this->getModelData();
            
            if (empty($modelData['columns'])) {
                throw new \InvalidArgumentException('No columns defined in modelData');
            }

            // Use basePath from modelData if set, otherwise from command option, fallback to base_path()
            $basePath = $modelData['basePath'] ?? base_path();

            $this->info('Starting CRUD generation...');
            $this->info('Using base path: ' . $basePath);

            $this->info('Generating Laravel files...');
            $laravelGenerator = new LaravelGenerator(
                $basePath, 
                array_merge($modelData, ['basePath' => $basePath]),
                $this
            );
            $laravelGenerator->generate();

            $this->info('Generating Vue files...');
            $vueGenerator = new VueGenerator(
                $basePath,
                array_merge($modelData, ['basePath' => $basePath]),
                $this
            );
            $vueGenerator->generate();

            $this->info('CRUD generation completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    public function setModelData(array $modelData): void
    {
        if (empty($modelData['columns'])) {
            throw new \InvalidArgumentException('Columns must be provided in modelData');
        }

        // Ensure all required fields are present
        $requiredFields = ['model', 'namespace', 'routePrefix', 'softDeletes', 'layout', 'columns', 'basePath'];
        foreach ($requiredFields as $field) {
            if (!isset($modelData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $this->modelData = $modelData;
        $this->info('Model data set: ' . json_encode($this->modelData, JSON_PRETTY_PRINT));
    }


    protected function getBaseRouterContent(): string
    {
        return <<<TS
import DefaultRouterView from '@/pages/DefaultRouterView.vue'
import App from "@/layouts/App.vue";
import NotFound from "@/pages/NotFound.vue";

export const appRoutes = [
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFound,
        meta: {
            title: 'routes.titles.not_found',
            description: 'routes.descriptions.not_found',
            requiresAuth: false
        }
    }
]
TS;
    }

    protected function getColumns(): array
    {
        // If columns were set via setColumns(), return those
        if (!empty($this->columns) && !$this->isInteractive) {
            return $this->columns;
        }

        // First try to get columns from command option
        $columnsOption = $this->option('columns');
        if ($columnsOption) {
            $columns = json_decode($columnsOption, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $columns;
            }
            $this->error('Invalid JSON format for columns');
        }

        // Then try to get from config
        $configColumns = config('redprint.columns');
        if ($configColumns && !$this->confirm('Would you like to define custom columns?', false)) {
            return $configColumns;
        }

        // If no columns defined, ask for them interactively
        $columns = [];
        do {
            $name = $this->ask('Column name');
            $type = $this->choice('Column type', $this->supportedDataTypes, 0);
            
            // Additional prompts for enum type
            $enumValues = [];
            if ($type === 'enum') {
                $enumValuesStr = $this->ask('Enter enum values (comma-separated)');
                $enumValues = array_map('trim', explode(',', $enumValuesStr));
            }
            
            $nullable = $this->confirm('Is this column nullable?', false);
            $default = $this->ask('Default value (press enter for none)');

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => $nullable,
                'default' => $default ?: null,
                'enumValues' => $enumValues
            ];

        } while ($this->confirm('Would you like to add another column?', true));

        return $columns;
    }

    protected function ensureDirectoriesExist(string $model): void
    {
        $basePath = $this->laravel->basePath();
        
        $directories = [
            $basePath . '/app/Models',
            $basePath . '/app/Http/Controllers',
            $basePath . '/app/Http/Resources',
            $basePath . '/resources/js/components/' . $model,
            $basePath . '/resources/js/pages',
            $basePath . '/resources/js/layouts',
            $basePath . '/resources/js/router',
            $basePath . '/database/migrations',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
                }
            }
        }

        // Ensure router file exists
        $routerFile = $basePath . '/resources/js/router/routes.ts';
        if (!file_exists($routerFile)) {
            file_put_contents($routerFile, $this->getBaseRouterContent());
        }
    }
}
