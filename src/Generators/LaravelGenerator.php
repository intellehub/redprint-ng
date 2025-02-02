<?php

namespace Shahnewaz\RedprintNg\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Shahnewaz\RedprintNg\Services\StubService;
use Shahnewaz\RedprintNg\Services\FileService;
use Illuminate\Support\Facades\File;

class LaravelGenerator
{
    private StubService $stubService;
    private FileService $fileService;
    private string $basePath;
    private array $modelData;
    private Command $command;

    public function __construct(string $basePath, array $modelData, Command $command)
    {
        if (empty($modelData['columns'])) {
            throw new \InvalidArgumentException('No columns defined in modelData');
        }

        $this->stubService = new StubService();
        $this->fileService = new FileService();
        $this->basePath = $basePath;
        $this->modelData = $modelData;
        $this->command = $command;
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
            $this->basePath . '/app/Http/Controllers/' . ($this->modelData['namespace'] ? $this->modelData['namespace'] : ''),
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
        $tableName = Str::plural(Str::lower($model));
        
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
                ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n\n    use SoftDeletes;" 
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
        $namespace = $this->modelData['namespace'] 
            ? "\\{$this->modelData['namespace']}" 
            : '';
        
        $content = $this->stubService->getStub('laravel/controller.stub');
        
        $content = $this->stubService->processStub($content, [
            '{{ namespace }}' => "App\\Http\\Controllers{$namespace}",
            '{{ modelName }}' => $model,
            '{{ columnAssignments }}' => $this->getColumnAssignments(),
            '{{ softDeleteMethods }}' => $this->modelData['softDeletes'] ? $this->getSoftDeleteMethods($model) : '',
        ]);
        
        return $this->fileService->createFile(
            "{$this->basePath}/app/Http/Controllers/{$model}Controller.php",
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
        $routePrefix = $this->modelData['routePrefix'] 
            ? "'{$this->modelData['routePrefix']}'" 
            : "''";
        $namespace = $this->modelData['namespace'] 
            ? "\\{$this->modelData['namespace']}" 
            : '';
        
        $routeContent = "\n\nuse App\\Http\\Controllers{$namespace}\\{$model}Controller;";
        $routeContent .= "\n\nRoute::prefix({$routePrefix})->middleware(['auth:api'])->group(function () {";
        $routeContent .= "\n    Route::get('" . Str::plural(Str::lower($model)) . "', [{$model}Controller::class, 'getIndex']);";
        $routeContent .= "\n    Route::get('" . Str::plural(Str::lower($model)) . "/{id}', [{$model}Controller::class, 'show']);";
        $routeContent .= "\n    Route::post('" . Str::plural(Str::lower($model)) . "/save', [{$model}Controller::class, 'save']);";
        $routeContent .= "\n    Route::delete('" . Str::plural(Str::lower($model)) . "/{id}', [{$model}Controller::class, 'delete']);";
        
        if ($this->modelData['softDeletes']) {
            $routeContent .= "\n    Route::delete('" . Str::plural(Str::lower($model)) . "/{id}/force', [{$model}Controller::class, 'deleteFromTrash']);";
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

    private function getMigrationType(string $type): string
    {
        return match($type) {
            'text' => 'text',
            'boolean' => 'boolean',
            'number' => 'integer',
            'datetime' => 'datetime',
            default => 'string',
        };
    }

    private function getResourceColumns(): string
    {
        $columns = [
            "            'id' => \$this->id,"
        ];
        
        foreach ($this->modelData['columns'] as $column) {
            $columns[] = "            '{$column['name']}' => \$this->{$column['name']},";
        }
        
        $columns[] = "            'created_at' => \$this->created_at,";
        $columns[] = "            'updated_at' => \$this->updated_at,";
        
        if ($this->modelData['softDeletes']) {
            $columns[] = "            'deleted_at' => \$this->deleted_at,";
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