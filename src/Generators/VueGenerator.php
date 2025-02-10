<?php

namespace Shahnewaz\RedprintNg\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Shahnewaz\RedprintNg\Services\StubService;
use Shahnewaz\RedprintNg\Services\FileService;
use Illuminate\Support\Facades\File;

class VueGenerator
{
    private StubService $stubService;
    private FileService $fileService;
    private string $basePath;
    private array $modelData;
    private Command $command;

    private array $inputTypeMap = [
        // Numbers
        'integer' => 'number',
        'bigInt' => 'number',
        'float' => 'number',
        'decimal' => 'number',

        // Dates
        'timestamp' => 'datetime',
        'dateTime' => 'datetime',
        'date' => 'datetime',

        // Text
        'text' => 'text',
        'longText' => 'text',
        'mediumText' => 'text',

        // Boolean
        'boolean' => 'boolean',
        'tinyInteger' => 'boolean',

        // Default
        'string' => 'string'
    ];

    public function __construct(string $basePath, array $modelData, Command $command)
    {
        error_log("VueGenerator constructor - basePath: " . $basePath);
        error_log("VueGenerator constructor - modelData basePath: " . ($modelData['basePath'] ?? 'not set'));
        
        $this->stubService = new StubService();
        $this->fileService = new FileService();
        $this->basePath = $basePath;
        $this->modelData = $modelData;
        $this->command = $command;
    }

    public function generate()
    {
        $this->createDirectories();
        $this->generateCommonComponents();
        $this->generateIndexComponent();
        $this->generateFormComponent();
        $this->updateRouter();
    }

    protected function createDirectories()
    {
        $directories = [
            $this->basePath . '/resources/js/components/' . $this->modelData['namespace'] . '/' . $this->modelData['model'],
            $this->basePath . '/resources/js/router',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
        }
    }

    private function processVueStub(string $stubContent): string
    {
        $model = $this->modelData['model'] ?? '';
        $namespace = $this->modelData['namespace'] ?? '';

        // Common replacements used across multiple stubs
        $replacements = [
            // Model related
            '{{ modelName }}' => $model,
            '{{ modelLower }}' => Str::lower($model),
            '{{ modelPlural }}' => Str::plural(Str::lower($model)),

            // Namespace related
            '{{ namespace }}' => $namespace,
            '{{ namespaceLower }}' => Str::lower($namespace),
            '{{ routeNamespace }}' => $this->getRoutNamespace(),

            // Route related
            '{{ routeName }}' => $this->getRouteName(),
            '{{ routePath }}' => $this->getRouteName(),

            // Axios related
            '{{ axiosImport }}' => $this->getAxiosImport(),
            '{{ axiosInstance }}' => $this->modelData['axios_instance'] ?? 'axiosInstance',

            // Table related
            '{{ tableHeaderItems }}' => $this->getTableHeaderItems(),
            '{{ tableBodyItems }}' => $this->getTableBodyItems(),
            '{{ modelFirstColumn }}' => $this->getFirstColumn(),

            // Form related
            '{{ inputFields }}' => $this->getInputFields(),
            '{{ formInputVariables }}' => $this->getFormInputVariables(),

            // Relationship related (always include with empty default)
            '{{ relationshipDataVariables }}' => $this->getRelationshipDataVariables(),
            '{{ relationshipDataFetchers }}' => $this->generateRelationshipDataFetchers(),
            '{{ relationshipDataFetcherMethodCalls }}' => $this->generateRelationshipDataFetcherMethodCalls(),

            '{{ endpoint }}' => $this->modelData['endpoint'] ?? '',
            '{{ componentName }}' => $this->modelData['componentName'] ?? '',
            '{{ searchColumn }}' => $this->modelData['searchColumn'] ?? '',
        ];

        return $this->stubService->processStub($stubContent, $replacements);
    }

    // Helper method to get first column
    private function getFirstColumn(): string
    {
        return $this->modelData['columns'][0]['name'] ?? 'id';
    }

    // Update the generate methods to use the new processVueStub
    public function generateIndexComponent(): bool
    {
        $content = $this->stubService->getStub('vue/index.stub');
        $processedContent = $this->processVueStub($content);

        return $this->fileService->createFile(
            "{$this->basePath}/resources/js/components/{$this->modelData['namespace']}/{$this->modelData['model']}/{$this->modelData['model']}Index.vue",
            $processedContent
        );
    }

    public function generateFormComponent(): bool
    {
        $content = $this->stubService->getStub('vue/form.stub');
        $processedContent = $this->processVueStub($content);

        return $this->fileService->createFile(
            "{$this->basePath}/resources/js/components/{$this->modelData['namespace']}/{$this->modelData['model']}/{$this->modelData['model']}Form.vue",
            $processedContent
        );
    }

    public function updateRouter(): bool
    {
        $baseRoutePath = config('redprint.vue_router_location', 'resources/js/router/routes.ts');
        $mainRouterPath = "{$this->basePath}/" . $baseRoutePath;
        $routerDir = dirname($mainRouterPath);

        // Generate the new routes file
        $routeStub = $this->stubService->getStub('vue/route.stub');
        $newRoute = $this->processVueStub($routeStub);

        // Save the new routes file
        $newRouterPath = "{$routerDir}/{$this->modelData['model']}Routes.ts";
        if (!$this->fileService->createFile($newRouterPath, $newRoute)) {
            return false;
        }

        // Update main routes.ts file
        return $this->updateMainRouter($mainRouterPath);
    }

    // Helper method to update main router file
    private function updateMainRouter(string $mainRouterPath): bool
    {
        if (!file_exists($mainRouterPath)) {
            $this->command->error("routes.ts not found at: {$mainRouterPath}");
            return false;
        }

        $routerContent = file_get_contents($mainRouterPath);
        $model = $this->modelData['model'];

        // Add import
        $lastImportPos = strrpos($routerContent, 'import');
        $insertImport = "\nimport { {$model}Routes } from \"@/router/{$model}Routes\";\n";
        $routerContent = substr_replace($routerContent, $insertImport, $lastImportPos, 0);

        // Add route
        $routesArrayEnd = strrpos($routerContent, ']');
        if ($routesArrayEnd === false) {
            $this->command->error("Could not find routes array in routes.ts");
            return false;
        }

        // Get content up to the closing bracket
        $beforeClosing = substr($routerContent, 0, $routesArrayEnd);

        // Find the last actual route entry
        if (preg_match('/\s+(\w+Routes)\s*$/', $beforeClosing, $matches)) {
            $lastRoute = $matches[1];
            $lastRoutePos = strrpos($beforeClosing, $lastRoute);

            // Replace the last route with the same route plus a comma
            $routerContent = substr_replace(
                $routerContent,
                $lastRoute . ',',
                $lastRoutePos,
                strlen($lastRoute)
            );
        }

        // Add our new route
        $newRouteEntry = "\n    {$model}Routes\n";
        $routerContent = substr_replace($routerContent, $newRouteEntry, $routesArrayEnd, 0);

        return $this->fileService->createFile($mainRouterPath, $routerContent);
    }

    public function generateCommonComponents(): bool
    {
        $commonComponents = ['Empty', 'FormError', 'InputGroup', 'DefaultRouterView'];

        foreach ($commonComponents as $component) {
            // Load from .stub file
            $content = $this->stubService->getStub("vue/common/{$component}.stub");

            // Use copyFile for pre-formatted common components
            $success = $this->fileService->copyFile(
                "{$this->basePath}/resources/js/components/Common/{$component}.vue",
                $content
            );

            if (!$success) {
                $this->command->error("Failed to create {$component} component");
                return false;
            }

            $this->command->info("{$component} component created successfully");
        }

        return true;
    }

    private function getRouteName(): string
    {
        // Convert BlogPost to blog-posts
        return Str::plural(Str::kebab($this->modelData['model'] ?? ''));
    }

    private function getRoutNamespace(): string
    {
        return Str::lower($this->modelData['namespace'] ?? '');
    }

    private function getAxiosImport(): string
    {
        $axiosInstance = $this->modelData['axios_instance'] ?? null;
        if ($axiosInstance) {
            return "// Using configured axios instance: {$axiosInstance}";
        }

        return <<<JS
import axios from 'axios'

// Create axios instance with base URL
const axiosInstance = axios.create({
    baseURL: '/api/v1'
})
JS;
    }

    private function processColumnDefinitions(): array
    {
        $columns = [];
        foreach ($this->modelData['columns'] as $column) {
            $columns[] = [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'] ?? false,
                'default' => $column['default'] ?? null
            ];
        }
        return $columns;
    }

    private function getTableHeaderItems(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new RuntimeException('No columns defined in modelData');
        }

        $headers = [];
        foreach ($this->modelData['columns'] as $column) {
            $headers[] = <<<HTML
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8">{{ \$t('common.{$column['name']}') }}</th>
HTML;
        }

        # DEBUG: error_log("Generated headers: " . print_r($headers, true));
        return implode("\n", $headers);
    }

    private function getTableBodyItems(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new RuntimeException('No columns defined in modelData');
        }

        $rows = [];
        foreach ($this->modelData['columns'] as $column) {
            $rows[] = <<<HTML
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">{{ item.{$column['name']} }}</td>
HTML;
        }

        # DEBUG: error_log("Generated body rows: " . print_r($rows, true));
        return implode("\n", $rows);
    }

    private function getInputFields(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new RuntimeException('No columns defined in model. Aborting...');
        }

        $fields = [];
        foreach ($this->modelData['columns'] as $column) {
            $fields[] = $this->generateInputField($column);
        }

        # DEBUG: error_log("Generated input fields: " . print_r($fields, true));
        return implode("\n        ", $fields);
    }

    private function getFormInputVariables(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new RuntimeException('No columns defined in model. Aborting...');
        }

        $variables = [];
        $i = 0;
        foreach ($this->modelData['columns'] as $column) {
            if ($i == 0) {
                $variables[] = "{$column['name']}: null,";
            } else {
                $variables[] = "        {$column['name']}: null,";
            }
            $i = $i + 1;
        }

        # DEBUG:  error_log("Generated form variables: " . print_r($variables, true));
        return implode("\n", $variables);
    }

    private function generateInputField(array $column): string
    {
        if (!empty($column['relationshipData'])) {
            // Use select stub for relationship fields
            $stub = $this->stubService->getStub('vue/form/select.stub');
            return $this->stubService->processStub($stub, [
                '{{ relatedModelLower }}' => $column['relationshipData']['relatedModelLower'],
                '{{ relatedColumn }}' => $column['name'],
                '{{ relatedModeLabelColumn }}' => $column['relationshipData']['labelColumn'],
                '{{ relatedApiEndpoint }}' => $column['relationshipData']['endpoint']
            ]);
        }

        $type = $this->getInputType($column['type']);
        $mappedType = match ($type) {
            default => $type
        };

        $stubName = "vue/form/{$mappedType}.stub";

        $stub = $this->stubService->getStub($stubName);
        return $this->stubService->processStub($stub, [
            '{{ name }}' => $column['name']
        ]);
    }

    private function getRelationshipDataVariables(): string
    {
        $variables = [];
        foreach ($this->modelData['columns'] as $column) {
            if (!empty($column['relationshipData'])) {
                $relatedModelLower = $column['relationshipData']['relatedModelLower'];
                $variables[] = "{$relatedModelLower}Data: [],";
            }
        }
        return !empty($variables) ? implode("\n            ", $variables) : '';
    }

    private function generateRelationshipDataFetchers(): string
    {
        $fetchers = [];
        foreach ($this->modelData['columns'] as $column) {
            if (!empty($column['relationshipData'])) {
                $stub = $this->stubService->getStub('vue/fetchData.stub');
                $fetchers[] = $this->stubService->processStub($stub, [
                        '{{ axiosInstance }}' => $this->modelData['axios_instance'] ?? 'axiosInstance',
                        '{{ relatedModelTitleCase }}' => Str::title($column['relationshipData']['relatedModelLower']),
                        '{{ relatedModelLower }}' => $column['relationshipData']['relatedModelLower'],
                        '{{ relatedApiEndpoint }}' => $column['relationshipData']['endpoint']
                    ]) . ',';
            }
        }
        return !empty($fetchers) ? implode("\n        ", $fetchers) : '';
    }

    private function generateRelationshipDataFetcherMethodCalls(): string
    {
        $fetcherCalls = [];
        foreach ($this->modelData['columns'] as $column) {
            if (!empty($column['relationshipData'])) {
                $fetcherCalls[] = 'this.fetch' . Str::title($column['relationshipData']['relatedModelLower']) . 'Data()';
            }
        }
        return !empty($fetcherCalls) ? implode("\n        ", $fetcherCalls) : '';
    }

    private function getInputType(string $columnType): string
    {
        return $this->inputTypeMap[strtolower($columnType)] ?? 'string';
    }

    public function normalizePath(string $path): string
    {
        error_log("Normalizing path: " . $path);
        // Remove @/ prefix if present
        $path = preg_replace('/^@\//', 'resources/js/', $path);
        error_log("After normalization: " . $path);
        return $path;
    }

    public function generateBlankComponent(string $path): bool
    {
        try {
            error_log("Generating blank component at path: " . $path);
            error_log("Current basePath: " . $this->basePath);
            error_log("Current modelData basePath: " . ($this->modelData['basePath'] ?? 'not set'));
            
            $content = $this->stubService->getStub('vue/component.stub');
            $processedContent = $this->stubService->processStub($content, [
                '{{ componentName }}' => basename($path, '.vue'),
            ]);
            
            $fullPath = $this->basePath . '/'. $path;
            error_log("Full path for file creation: " . $fullPath);
            
            return $this->fileService->createFile($fullPath, $processedContent);
        } catch (\Exception $e) {
            error_log("Error generating blank component: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function generateListPageComponent(string $path): bool
    {
        $content = $this->stubService->getStub('vue/listPage.stub');
        $processedContent = $this->processVueStub($content);

        $fullPath = $this->basePath . '/'. $path;

        return $this->fileService->createFile($fullPath, $processedContent);
    }

    public function generateFormPageComponent(string $path): bool
    {
        $content = $this->stubService->getStub('vue/formPage.stub');
        $processedContent = $this->processVueStub($content);
        $fullPath = $this->basePath . '/'. $path;

        return $this->fileService->createFile($fullPath, $processedContent);
    }

    public function setModelData(array $data): void
    {
        $this->modelData = $data;
    }
} 