<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Facades\File;

class MakeCrudCommand extends Command
{
    protected $signature = 'redprint:crud 
        {--model= : The name of the model}
        {--namespace= : The namespace for the controller}
        {--route-prefix= : The route prefix}
        {--soft-deletes=false : Whether to include soft deletes}
        {--layout= : The layout component to wrap the page with}
        {--columns= : JSON string of columns configuration}';

    protected $description = 'Create a new CRUD module';

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
    private $basePath;
    private $isInteractive = true;

    protected function configure()
    {
        $this->addOption(
            'columns',
            null,
            InputOption::VALUE_OPTIONAL,
            'JSON string of columns configuration'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Get command options
            $model = $input->getOption('model');
            if (!$model) {
                $output->writeln('<error>Model name is required!</error>');
                return 1;
            }

            $namespace = $input->getOption('namespace');
            $routePrefix = $input->getOption('route-prefix');
            $softDeletes = $input->getOption('soft-deletes');
            $layout = $input->getOption('layout');
            $axiosInstance = config('redprint.axios_instance', 'axios');

            // Use provided base path or fall back to Laravel's base_path()
            $this->basePath = $this->basePath ?? base_path();

            // Create necessary directories
            $this->createDirectories($this->basePath);

            // Create route files
            $this->createRouteFiles();

            // Example of reading from api.php
            $apiFilePath = $this->basePath . '/routes/api.php';
            if (File::exists($apiFilePath)) {
                $apiContent = File::get($apiFilePath);
                // Process the content if needed
            }

            // Skip prompting if columns are already set
            if (empty($this->columns) && $this->isInteractive) {
                $this->columns = $this->promptForColumns();
            }

            // Validate columns
            if (empty($this->columns)) {
                $output->writeln('<error>No columns defined!</error>');
                return 1;
            }

            // Generate files
            $this->generateMigration($model, $this->columns, $softDeletes);
            $this->generateModel($model, $namespace, $softDeletes);
            $this->generateController($model, $namespace);
            $this->generateResource($model);
            $this->generateVueComponents($model, $layout, $this->columns);
            $this->updateRoutes($model, $namespace, $routePrefix);

            $output->writeln('<info>CRUD generated successfully!</info>');
            return 0;

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    private function checkDependencies()
    {
        $packageJson = base_path('package.json');
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
        $commonPath = resource_path('js/components/Common');
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
        $path = $this->basePath . '/app/Http/Controllers/' . ($namespace ? "{$namespace}/" : "");
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

    private function getSoftDeleteMethods($model)
    {
        return "
        public function deleteFromTrash(\$id)
        {
            \$model = {$model}::withTrashed()->findOrFail(\$id);
            \$model->forceDelete();
            
            return response()->json(['message' => '{$model} permanently deleted'], 204);
        }";
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
        file_put_contents(database_path("migrations/{$fileName}"), $stub);
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
        $pagesPath = resource_path('js/pages');
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

    private function generateVueComponents($model, $layout, $columns)
    {
        // Generate form fields configuration
        $formFields = [];
        foreach ($columns as $column) {
            $type = $column['type'];
            $method = 'generate' . ucfirst($type) . 'Field';
            
            if (method_exists($this, $method)) {
                $formFields[] = $this->$method($column);
            } else {
                // Fallback to string field if no specific generator exists
                $formFields[] = $this->generateStringField($column);
            }
        }

        // Convert form fields to JSON for template
        $formFieldsJson = json_encode($formFields, JSON_PRETTY_PRINT);

        // Create the Vue components
        $this->info("Creating Vue components for {$model}...");

        // Use the base path for file generation
        $basePath = $this->basePath ?? base_path();

        // Generate page component
        $pageStub = $this->getStub('vue/page.stub');
        $pageComponent = str_replace(
            ['{{modelName}}', '{{formFields}}'],
            [$model, $formFieldsJson],
            $pageStub
        );
        
        $pagePath = $basePath . "/resources/js/pages/{$model}.vue";
        File::put($pagePath, $pageComponent);
        $this->info("{$model} page component created successfully.");

        // Generate index component
        $indexStub = $this->getStub('vue/index.stub');
        $indexComponent = str_replace('{{modelName}}', $model, $indexStub);
        
        $indexPath = $basePath . "/resources/js/components/{$model}/Index.vue";
        File::put($indexPath, $indexComponent);
        $this->info("{$model} index component created successfully.");

        // Generate form component
        $formStub = $this->getStub('vue/form.stub');
        $formComponent = str_replace(
            ['{{modelName}}', '{{formFields}}'],
            [$model, $formFieldsJson],
            $formStub
        );
        
        $formPath = $basePath . "/resources/js/components/{$model}/Form.vue";
        File::put($formPath, $formComponent);
        $this->info("{$model} form component created successfully.");
    }

    private function getStub($name)
    {
        $stubPath = __DIR__ . '/../stubs/' . $name;

        if (!File::exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubPath}");
        }

        return File::get($stubPath);
    }

    private function updateRoutes($model, $namespace, $routePrefix)
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

    private function getAxiosInstance()
    {
        return config('redprint.axios_instance') ?? 'axios';
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

    private function generateStringField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'text',
            'rules' => $nullable ? [] : ['required'],
            'default' => $default ?? '',
            'placeholder' => "Enter {$name}",
            'class' => 'form-control'
        ];
    }

    private function generateTextField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'textarea',
            'rules' => $nullable ? [] : ['required'],
            'default' => $default ?? '',
            'placeholder' => "Enter {$name}",
            'class' => 'form-control'
        ];
    }

    private function generateBooleanField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'switch',
            'rules' => $nullable ? [] : ['required'],
            'default' => $default ?? false,
            'class' => 'form-control'
        ];
    }

    private function generateNumberField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'number',
            'rules' => $nullable ? [] : ['required', 'numeric'],
            'default' => $default ?? 0,
            'placeholder' => "Enter {$name}",
            'class' => 'form-control'
        ];
    }

    private function generateDateField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'date',
            'rules' => $nullable ? [] : ['required', 'date'],
            'default' => $default ?? null,
            'class' => 'form-control'
        ];
    }

    private function generateDateTimeField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'datetime-local',
            'rules' => $nullable ? [] : ['required', 'date'],
            'default' => $default ?? null,
            'class' => 'form-control'
        ];
    }

    private function generateTimeField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'time',
            'rules' => $nullable ? [] : ['required'],
            'default' => $default ?? null,
            'class' => 'form-control'
        ];
    }

    private function generateJsonField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'textarea',
            'rules' => $nullable ? [] : ['required', 'json'],
            'default' => $default ?? '{}',
            'placeholder' => "Enter JSON data",
            'class' => 'form-control'
        ];
    }

    private function generateEnumField($field)
    {
        $name = $field['name'];
        $nullable = $field['nullable'];
        $default = $field['default'];

        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'select',
            'rules' => $nullable ? [] : ['required'],
            'default' => $default ?? null,
            'options' => [], // This should be populated with enum values
            'class' => 'form-control'
        ];
    }

    private function generateDefaultField($type)
    {
        switch ($type) {
            case 'string':
                return "''";
            case 'integer':
            case 'bigInteger':
            case 'float':
            case 'double':
            case 'decimal':
                return '0';
            case 'boolean':
                return 'false';
            case 'date':
            case 'dateTime':
            case 'time':
                return 'null';
            case 'text':
            case 'json':
                return "''";
            case 'enum':
                return 'null';
            default:
                return 'null';
        }
    }

    private function createDirectories($basePath)
    {
        $directories = [
            $basePath . '/app/Models',
            $basePath . '/app/Http/Controllers/Blog',
            $basePath . '/app/Http/Resources',
            $basePath . '/resources/js/pages',
            $basePath . '/resources/js/components',
            $basePath . '/resources/js/components/Post',
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
        return $this->getStub('api.stub');
    }
}
