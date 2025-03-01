<?php

namespace Shahnewaz\RedprintNg\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Shahnewaz\RedprintNg\Services\StubService;
use Shahnewaz\RedprintNg\Services\FileService;
use Illuminate\Support\Facades\File;
use Shahnewaz\RedprintNg\Enums\DataTypes;

class LaravelGenerator
{
    private StubService $stubService;
    private FileService $fileService;
    private string $basePath;
    private array $modelData;

    public function __construct(string $basePath, array $modelData, Command $command)
    {
        if (empty($modelData['columns'])) {
            throw new \InvalidArgumentException('No columns defined in modelData');
        }

        $this->stubService = new StubService();
        $this->fileService = new FileService();
        $this->basePath = $basePath;
        $this->modelData = $modelData;
    }

    public function generate(): void
    {
        $this->createDirectories();
        $this->generateModel();
        $this->generateController();
        $this->generateResource();
        $this->generateMigration();
        $this->updateRoutes();
    }

    protected function createDirectories(): void
    {
        $directories = [
            $this->basePath . '/app/Models',
            $this->basePath . '/app/Http/Controllers/Api/' . ($this->modelData['namespace'] ?: ''),
            $this->basePath . '/app/Http/Resources',
            $this->basePath . '/database/migrations',
            $this->basePath . '/routes',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
        }
    }

    public function generateMigration(): bool
    {
        $model = $this->modelData['model'];
        $tableName = Str::snake(Str::plural($model));
        
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$tableName}_table.php";
        
        $content = $this->stubService->getStub('laravel/migration.stub');
        
        $content = $this->stubService->processStub($content, [
            '{{ tableName }}' => $tableName,
            '{{ columnDefinitions }}' => $this->getColumnDefinitions(),
            '{{ softDeletesColumn }}' => $this->modelData['softDeletes'] ? "\$table->softDeletes();\n            " : '',
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/database/migrations/{$filename}",
            $content
        );
    }

    public function generateModel(): bool
    {
        $model = $this->modelData['model'];
        $content = $this->stubService->getStub('laravel/model.stub');
        
        $fillable = $this->getFillableColumns();
        
        $content = $this->stubService->processStub($content, [
            '{{ namespace }}' => "App\\Models",
            '{{ modelName }}' => $model,
            '{{ fillable }}' => $fillable,
            '{{ useSoftDeletes }}' => $this->modelData['softDeletes'] 
                ?"use SoftDeletes;" 
                : '',
            '{{ importSoftDeletes }}' => $this->modelData['softDeletes'] 
                ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;" 
                : '',
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/app/Models/{$model}.php",
            $content
        );
    }

    public function generateController(): bool
    {
        $model = $this->modelData['model'];
        $namespaceString = $this->modelData['namespace']
            ? "\\{$this->modelData['namespace']}" 
            : '';
        $namespace = $this->modelData['namespace'] ?: '';
        
        $content = $this->stubService->getStub('laravel/controller.stub');
        
        $content = $this->stubService->processStub($content, [
            '{{ namespace }}' => "App\\Http\\Controllers\\Api{$namespaceString}",
            '{{ modelName }}' => $model,
            '{{ columnAssignments }}' => $this->getColumnAssignments(),
            '{{ softDeleteMethods }}' => $this->modelData['softDeletes'] ? $this->getSoftDeleteMethods($model) : '',
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/app/Http/Controllers/Api/$namespace/{$model}Controller.php",
            $content
        );
    }

    public function generateResource(): bool
    {
        $model = $this->modelData['model'];
        $content = $this->stubService->getStub('laravel/resource.stub');
        
        $content = $this->stubService->processStub($content, [
            '{{ modelName }}' => $model,
            '{{ columns }}' => $this->getResourceColumns(),
            '{{ softDeletesResource }}' => $this->modelData['softDeletes'] ? "'deleted_at' => \$this->deleted_at," : ''
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/app/Http/Resources/{$model}Resource.php",
            $content
        );
    }

    public function updateRoutes(): bool
    {
        $model = $this->modelData['model'];
        $routePath = Str::plural(Str::kebab($model));
        
        $routePrefix = $this->modelData['routePrefix'] 
            ? $this->modelData['routePrefix'].'/'.Str::lower($this->modelData['namespace'])
            : Str::lower($this->modelData['namespace']);
        $namespace = $this->modelData['namespace'] 
            ? "\\{$this->modelData['namespace']}"
            : '';
        
        $routeContent = "\n\nuse App\\Http\\Controllers\\Api{$namespace}\\{$model}Controller;";
        $routeContent .= "\n\nRoute::prefix('{$routePrefix}')->middleware(['auth:api'])->group(function () {";
        $routeContent .= "\n    Route::get('{$routePath}', [{$model}Controller::class, 'getIndex']);";
        $routeContent .= "\n    Route::get('{$routePath}/list', [{$model}Controller::class, 'listAll']);";
        $routeContent .= "\n    Route::get('{$routePath}/{id}', [{$model}Controller::class, 'show']);";
        $routeContent .= "\n    Route::post('{$routePath}/save', [{$model}Controller::class, 'save']);";
        $routeContent .= "\n    Route::delete('{$routePath}/{id}', [{$model}Controller::class, 'delete']);";
        
        if ($this->modelData['softDeletes']) {
            $routeContent .= "\n    Route::delete('{$routePath}/{id}/force', [{$model}Controller::class, 'deleteFromTrash']);";
        }
        
        $routeContent .= "\n});";
        
        return file_put_contents(
            "{$this->basePath}/routes/api.php",
            $routeContent,
            FILE_APPEND
        ) !== false;
    }

    private function getSoftDeleteMethods(string $model): string
    {
        $content = $this->stubService->getStub('laravel/softDeleteMethods.stub');
        
        return $this->stubService->processStub($content, [
            '{{ model }}' => $model
        ]);
    }

    private function getColumnDefinitions(): string
    {
        if (empty($this->modelData['columns'])) {
            return '';
        }

        $definitions = [];
        foreach ($this->modelData['columns'] as $column) {
            $type = $this->getMigrationType($column['type']);
            $definition = "\$table->{$type}('{$column['name']}')";
            
            if ($column['nullable'] ?? false) {
                $definition .= "->nullable()";
            }
            
            if (isset($column['default'])) {
                $value = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
                $definition .= "->default({$value})";
            }
            
            $definitions[] = $definition . ";";
        }
        
        return implode("\n            ", $definitions);
    }

    // Then map these chosen types to Laravel migration types
    private function getMigrationType(string $chosenType): string
    {
        return DataTypes::from($chosenType)->getMigrationType();
    }

    private function getResourceColumns(): string
    {
        $columns = [
            "'id' => (int) \$this->id,"
        ];
        
        foreach ($this->modelData['columns'] as $column) {
            $type = DataTypes::from($column['type']);
            $castedValue = match($type) {
                // Integer types
                DataTypes::INTEGER,
                DataTypes::BIG_INTEGER,
                DataTypes::SMALL_INTEGER,
                DataTypes::TINY_INTEGER,
                DataTypes::UNSIGNED_INTEGER,
                DataTypes::UNSIGNED_BIG_INTEGER => "(int) \$this->{$column['name']},",
                
                // Float types
                DataTypes::FLOAT,
                DataTypes::DOUBLE,
                DataTypes::DECIMAL => "(float) \$this->{$column['name']},",
                
                // Boolean type
                DataTypes::BOOLEAN => "(bool) \$this->{$column['name']},",
                
                // DateTime types
                DataTypes::DATETIME,
                DataTypes::TIMESTAMP => "\$this->{$column['name']}?->format('Y-m-d H:i:s'),",
                
                // Date type
                DataTypes::DATE => "\$this->{$column['name']}?->format('Y-m-d'),",
                
                // Time type
                DataTypes::TIME => "\$this->{$column['name']}?->format('H:i:s'),",
                
                // Year type
                DataTypes::YEAR => "\$this->{$column['name']}?->format('Y'),",
                
                // JSON types
                DataTypes::JSON,
                DataTypes::JSONB => "json_decode(\$this->{$column['name']}, true),",
                
                // Default for string types and others
                default => "\$this->{$column['name']},"
            };
            
            $columns[] = "            '{$column['name']}' => " . $castedValue;
        }
        
        $columns[] = "            'created_at' => \$this->created_at->format('Y-m-d H:i:s'),";
        $columns[] = "            'updated_at' => \$this->updated_at->format('Y-m-d H:i:s'),";
        
        if ($this->modelData['softDeletes']) {
            $columns[] = "            'deleted_at' => \$this->deleted_at?->format('Y-m-d H:i:s'),";
        }
        
        return implode("\n", $columns);
    }

    private function getColumnAssignments(): string
    {
        if (empty($this->modelData['columns'])) {
            return '';
        }

        $assignments = [];
        foreach ($this->modelData['columns'] as $column) {
            $assignments[] = "\$model->{$column['name']} = \$request->input('{$column['name']}');";
        }
        
        return implode("\n        ", $assignments);
    }

    private function getFillableColumns(): string
    {
        $fillable = array_map(function($column) {
            return "'{$column['name']}'";
        }, $this->modelData['columns']);
        
        return implode(', ', $fillable);
    }
} 