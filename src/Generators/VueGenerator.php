<?php

namespace Shahnewaz\RedprintNg\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
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

    public function __construct(string $basePath, array $modelData, Command $command)
    {
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
        $this->generatePageComponent();
        $this->updateRouter();
    }

    protected function createDirectories()
    {
        $directories = [
            $this->basePath . '/resources/js/components/' . $this->modelData['model'],
            $this->basePath . '/resources/js/pages',
            $this->basePath . '/resources/js/router',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
        }
    }

    public function generateIndexComponent(): bool
    {
        $model = $this->modelData['model'];
        $content = $this->stubService->getStub('vue/index.stub');
        
        $routeName = $this->getRouteName();
        $columns = $this->processColumnDefinitions();
        $firstColumn = $columns[0]['name'] ?? 'id';
        
        $content = $this->stubService->processStub($content, [
            '{{ modelName }}' => $model,
            '{{ routeName }}' => $routeName,
            '{{ routePath }}' => $routeName,
            '{{ axiosImport }}' => $this->getAxiosImport(),
            '{{ axiosInstance }}' => $this->modelData['axios_instance'] ?? 'axiosInstance',
            '{{ modelFirstColumn }}' => $firstColumn,
            '{{ tableHeaderItems }}' => $this->getTableHeaderItems(),
            '{{ tableBodyItems }}' => $this->getTableBodyItems()
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/resources/js/components/{$model}/Index.vue",
            $content
        );
    }

    public function generateFormComponent(): bool
    {
        $model = $this->modelData['model'];
        $content = $this->stubService->getStub('vue/form.stub');
        
        $routeName = $this->getRouteName();
        $axiosImport = $this->getAxiosImport();
        $columns = $this->processColumnDefinitions();
        
        $content = $this->stubService->processStub($content, [
            '{{ modelName }}' => $model,
            '{{ routeName }}' => $routeName,
            '{{ routePath }}' => $routeName,
            '{{ axiosImport }}' => $axiosImport,
            '{{ axiosInstance }}' => $this->modelData['axios_instance'] ?? 'axiosInstance',
            '{{ inputFields }}' => $this->getInputFields(),
            '{{ formInputVariables }}' => $this->getFormInputVariables()
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/resources/js/components/{$model}/Form.vue",
            $content
        );
    }

    public function generatePageComponent(): bool
    {
        // Choose the appropriate stub based on whether a layout is specified
        $stubName = $this->modelData['layout'] 
            ? 'vue/page-with-layout.stub'
            : 'vue/page.stub';
        
        $replacements = [
            'modelName' => $this->modelData['model'],
            'routePrefix' => $this->modelData['routePrefix']
        ];

        // Add layout replacement if using layout
        if ($this->modelData['layout']) {
            $replacements['layout'] = $this->modelData['layout'];
        }

        $content = $this->stubService->processStub(
            $this->stubService->getStub($stubName),
            $replacements
        );

        $path = $this->basePath . '/resources/js/pages/' . 
                $this->modelData['model'] . '.vue';
        File::put($path, $content);

        return true;
    }

    public function updateRouter(): bool
    {
        $routerPath = "{$this->basePath}/resources/js/router/routes.ts";

        if (!file_exists($routerPath)) {
            $this->command->error("routes.ts not found at: {$routerPath}");
            return false;
        }

        $routerContent = file_get_contents($routerPath);

        // Find the appRoutes array
        if (preg_match('/export\s+const\s+appRoutes\s*=\s*\[([\s\S]*?)]/m', $routerContent, $matches)) {
            $existingRoutes = $matches[1];
            $newRoute = $this->getNewRouteConfig();

            // Add new route before the catch-all route
            $updatedRoutes = rtrim($existingRoutes);
            if (strpos($updatedRoutes, 'path: \'/:pathMatch') !== false) {
                // Insert before the catch-all route
                $updatedRoutes = str_replace(
                    '    {',
                    "    " . trim($newRoute) . ",\n    {",
                    $updatedRoutes
                );
            } else {
                // Append if no catch-all route found
                $updatedRoutes .= ($updatedRoutes ? ',' : '') . $newRoute;
            }

            $routerContent = str_replace(
                $matches[0],
                'export const appRoutes = [' . $updatedRoutes . ']',
                $routerContent
            );

            return $this->fileService->createFile($routerPath, $routerContent);
        }

        $this->command->error("Could not find appRoutes array in routes.ts");
        return false;
    }

    private function getNewRouteConfig(): string
    {
        $model = $this->modelData['model'];
        $modelLower = Str::lower($model);
        $modelPlural = Str::plural($modelLower);
        
        return $this->stubService->processStub(
            $this->stubService->getStub('vue/route.stub'),
            [
                '{{ model }}' => $model,
                '{{ modelLower }}' => $modelLower,
                '{{ modelPlural }}' => $modelPlural
            ]
        );
    }

    public function generateCommonComponents(): bool
    {
        $commonComponents = ['Empty', 'FormError', 'InputGroup'];
        
        foreach ($commonComponents as $component) {
            // Load from .stub file
            $content = $this->stubService->getStub("vue/common/{$component}.stub");
            
            // Save as .vue file
            $success = $this->fileService->createFile(
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
        $prefix = $this->modelData['routePrefix'] ?? 'api/v1';
        $modelPlural = Str::plural(Str::lower($this->modelData['model']));
        return trim("{$prefix}/{$modelPlural}", '/');
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
            throw new \RuntimeException('No columns defined in modelData');
        }

        $headers = [];
        foreach ($this->modelData['columns'] as $column) {
            $headers[] = <<<HTML
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8">{{ \$t('common.{$column['name']}') }}</th>
HTML;
        }
        
        error_log("Generated headers: " . print_r($headers, true));
        return implode("\n", $headers);
    }

    private function getTableBodyItems(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new \RuntimeException('No columns defined in modelData');
        }

        $rows = [];
        foreach ($this->modelData['columns'] as $column) {
            $rows[] = <<<HTML
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">{{ item.{$column['name']} }}</td>
HTML;
        }
        
        error_log("Generated body rows: " . print_r($rows, true));
        return implode("\n", $rows);
    }

    private function getInputFields(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new \RuntimeException('No columns defined in modelData');
        }

        $fields = [];
        foreach ($this->modelData['columns'] as $column) {
            $fields[] = $this->generateInputField($column);
        }
        
        error_log("Generated input fields: " . print_r($fields, true));
        return implode("\n        ", $fields);
    }

    private function getFormInputVariables(): string
    {
        if (empty($this->modelData['columns'])) {
            throw new \RuntimeException('No columns defined in modelData');
        }

        $variables = [];
        foreach ($this->modelData['columns'] as $column) {
            $variables[] = "                {$column['name']}: null,";
        }
        
        error_log("Generated form variables: " . print_r($variables, true));
        return implode("\n", $variables);
    }

    private function generateInputField(array $column): string
    {
        $inputType = $this->getInputType($column['type']);
        return <<<HTML
        <div class="mb-5.5 flex flex-col gap-5.5 sm:flex-row">
            <div class="w-full sm:w-1/2">
                <InputGroup 
                    id="{$column['name']}" 
                    :label="\$t('form.{$column['name']}')" 
                    type="{$inputType}" 
                    :placeholder="\$t('common.{$column['name']}')" 
                    v-model="form.{$column['name']}">
                </InputGroup>
                <form-error :errors="validationErrors" field="{$column['name']}"></form-error>
            </div>
        </div>
HTML;
    }

    private function getInputType(string $columnType): string
    {
        return match($columnType) {
            'text' => 'textarea',
            'boolean' => 'checkbox',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'time' => 'time',
            'email' => 'email',
            'password' => 'password',
            default => 'text',
        };
    }
} 