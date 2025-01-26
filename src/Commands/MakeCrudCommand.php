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

    protected $description = 'Create a complete CRUD setup including model, controller, views, and routes';

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
            $this->generateMigration($model, $softDeletes);
            
            // Generate Vue Components
            $this->generateVueComponents($model, $layout);
            
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

    private function generateMigration($model, $softDeletes)
    {
        $stub = file_get_contents(__DIR__ . '/../stubs/laravel/migration.stub');
        
        $tableName = Str::plural(Str::snake($model));
        $softDeletesColumn = $softDeletes ? '$table->softDeletes();' : '';

        $stub = str_replace(
            ['{{tableName}}', '{{softDeletesColumn}}'],
            [$tableName, $softDeletesColumn],
            $stub
        );

        $fileName = date('Y_m_d_His') . "_create_{$tableName}_table.php";
        file_put_contents(database_path("migrations/{$fileName}"), $stub);
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

    private function generateVueComponents($model, $layout = null)
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
        
        foreach ($components as $component) {
            if (file_exists($component['path'])) {
                $this->warn("{$model} {$component['name']} component already exists. Skipping...");
                continue;
            }

            $stub = file_get_contents(__DIR__ . "/../stubs/vue/{$component['stub']}");
            $stub = str_replace(
                [
                    '{{modelName}}',
                    '{{routeName}}',
                    '{{routePrefix}}',
                    '{{routePath}}',
                    '{{axiosInstance}}',
                    '{{axiosImport}}'
                ],
                [
                    $model,
                    $routeName,
                    $routePrefix,
                    $routeName,
                    $axiosInstance,
                    $axiosInstance === 'axios' ? "import axios from 'axios'" : ''
                ],
                $stub
            );
            
            file_put_contents($component['path'], $stub);
            $this->info("{$model} {$component['name']} component created successfully.");
        }

        $this->updateVueRouter($model, $routeName);
    }

    private function updateVueRouter($model, $routeName)
    {
        $routerFile = resource_path('js/routes.ts');
        $content = file_get_contents($routerFile);

        // Check if routes already exist
        if (strpos($content, "path: '/{$routeName}'") !== false) {
            $this->warn("Routes for {$model} already exist. Skipping route generation...");
            return;
        }

        // Find the last import statement
        $lastImportPos = strrpos($content, "import");
        if ($lastImportPos === false) {
            $lastImportPos = 0;
        } else {
            // Find the end of the last import line
            $lastImportPos = strpos($content, "\n", $lastImportPos) + 1;
        }

        // Add new imports after the last import
        $imports = "import {$model}Page from \"@/pages/{$model}.vue\";
import {$model}Index from \"@/components/{$model}/Index.vue\";
import {$model}Form from \"@/components/{$model}/Form.vue\";
";

        $content = substr_replace($content, $imports, $lastImportPos, 0);

        // Add routes before the last closing bracket
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

        // Check if routes already exist
        if (strpos($content, "{$routeName}/save") !== false) {
            $this->warn("Routes for {$model} already exist. Skipping...");
            return;
        }

        $routes = "
Route::prefix('" . ($routePrefix ? $routePrefix : '') . "')->middleware(['auth:api'])->group(function () {
    Route::get('{$routeName}', [{$controllerNamespace}::class, 'getIndex'])->name('{$routeName}.index');
    Route::post('{$routeName}/save', [{$controllerNamespace}::class, 'save'])->name('{$routeName}.save');
    Route::delete('{$routeName}/{id}', [{$controllerNamespace}::class, 'delete'])->name('{$routeName}.delete');";

        if ($this->option('soft-deletes') === 'true') {
            $routes .= "
    Route::delete('{$routeName}/{id}/force', [{$controllerNamespace}::class, 'deleteFromTrash'])->name('{$routeName}.force-delete');";
        }

        $routes .= "
});";

        file_put_contents($routesFile, $content . $routes);
        $this->info("API routes for {$model} added successfully.");
    }

    private function getAxiosInstance()
    {
        return config('redprint.axios_instance') ?? 'axios';
    }
}
