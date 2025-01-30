<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'redprint:crud 
        {--model= : The name of the model}
        {--namespace= : The namespace for the controller}
        {--route-prefix= : The route prefix}
        {--soft-deletes=false : Whether to include soft deletes}
        {--layout= : The layout component to wrap the page with}';

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

    public function handle()
    {
        try {
            $this->checkDependencies();

            $model = $this->option('model');
            $namespace = $this->option('namespace');
            $routePrefix = $this->option('route-prefix');
            $softDeletes = $this->option('soft-deletes') === 'true';
            $layout = $this->option('layout');

            if (empty($model)) {
                $this->error('Model name is required!');
                return 1;
            }

            // Validate layout if provided
            if ($layout) {
                $this->validateLayout($layout);
            }

            // Copy common components first
            $this->copyCommonComponents();

            // Generate Model
            $this->generateModel($model, $softDeletes);
            
            // Generate Controller
            $this->generateController($model, $namespace);
            
            // Generate Resource
            $this->generateResource($model);
            
            // Generate Migration
            $columns = $this->promptForColumns();
            $this->generateMigration($model, $softDeletes, $columns);
            
            // Generate Vue Components
            $this->generateVueComponents($model, $layout, $columns);
            
            // Update Routes
            $this->updateRoutes($model, $namespace, $routePrefix);

            $this->info('CRUD generated successfully!');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
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
        $filePath = app_path("Models/{$model}.php");
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
        $path = app_path("Http/Controllers/" . ($namespace ? "{$namespace}/" : ""));
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
        $path = app_path("Http/Resources/");
        $filePath = "{$path}{$model}Resource.php";

        if (file_exists($filePath)) {
            $this->warn("Resource {$model}Resource already exists. Skipping...");
            return;
        }

        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/resource.stub');
        
        $softDeletesResource = $this->option('soft-deletes') === 'true' 
            ? "'deleted_at' => \$this->deleted_at," 
            : '';

        $stub = str_replace(
            ['{{modelName}}', '{{softDeletesResource}}'],
            [$model, $softDeletesResource],
            $stub
        );

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($filePath, $stub);
        $this->info("Resource {$model}Resource created successfully.");
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

    private function generateMigration($model, $softDeletes, $columns)
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
        $routeName = Str::plural(Str::snake($model));
        $routePrefix = $this->option('route-prefix') ? $this->option('route-prefix') . '/' : '';
        
        // Generate page component with or without layout
        $this->generatePageComponent($model, $layout);

        $pagesPath = resource_path('js/pages');
        $componentsPath = resource_path("js/components/{$model}");
        
        // Create directories
        foreach ([$pagesPath, $componentsPath] as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }

        // Generate components with existence checks
        $components = [
            ['path' => "{$pagesPath}/{$model}.vue", 'stub' => 'page.stub', 'name' => 'Page'],
            ['path' => "{$componentsPath}/Index.vue", 'stub' => 'index.stub', 'name' => 'Index'],
            ['path' => "{$componentsPath}/Form.vue", 'stub' => 'form.stub', 'name' => 'Form']
        ];

        $axiosInstance = $this->getAxiosInstance();
        
        $replacements = [
            '{{modelName}}' => $model,
            '{{routeName}}' => $routeName,
            '{{routePrefix}}' => $routePrefix,
            '{{routePath}}' => $routeName,
            '{{columns}}' => json_encode($columns)
        ];

        // Process stubs with columns data
        foreach ($components as $component) {
            if (file_exists($component['path'])) {
                $this->warn("{$model} {$component['name']} component already exists. Skipping...");
                continue;
            }

            $stub = file_get_contents(__DIR__ . "/../stubs/vue/{$component['stub']}");
            $stub = $this->processStub($stub, $replacements, $axiosInstance);
            
            file_put_contents($component['path'], $stub);
            $this->info("{$model} {$component['name']} component created successfully.");
        }

        $this->generateVueRoutes($model);
    }

    private function processStub($stub, $replacements, $axiosInstance)
    {
        // Replace all standard placeholders
        $stub = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        // Handle axios instance conditionals
        if ($axiosInstance === 'axios') {
            $stub = str_replace(
                [
                    '{{axiosImport}}',
                    '{{axiosCall}}'
                ],
                [
                    "import axios from 'axios'\n\nconst api = axios.create({\n    baseURL: `${window.location.protocol}//${window.location.host}/api/{{routePrefix}}/`\n})",
                    'api'
                ],
                $stub
            );
        } else {
            $stub = str_replace(
                [
                    '{{axiosImport}}',
                    '{{axiosCall}}'
                ],
                [
                    '',
                    $axiosInstance
                ],
                $stub
            );
        }

        return $stub;
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

    private function updateRoutes($model, $namespace, $routePrefix)
    {
        $routesFile = base_path('routes/api.php');
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
}
