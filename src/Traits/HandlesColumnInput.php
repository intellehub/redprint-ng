<?php

namespace Shahnewaz\RedprintNg\Traits;

use Illuminate\Support\Str;

trait HandlesColumnInput
{
    private function promptForColumns(?string $namespace = null): array
    {
        $columns = [];
        $this->info("\nPlease enter column names" . ($namespace ? " for the {$namespace} module" : "") . ":");
        
        do {
            // Column name with validation
            do {
                $name = $this->ask('Column Name (lowercase letters and underscore only)');
                
                if (empty($name)) {
                    $this->error('Column name cannot be empty.');
                    continue;
                }
                
                if (!preg_match('/^[a-z_]+$/', $name)) {
                    $this->error('Column name must contain only lowercase letters and underscores.');
                    continue;
                }
                
                break;
            } while (true);
            
            $this->info('Column name: ' . $name);
            
            $type = $this->choice(
                'Select column type:',
                [
                    'string' => 'String',
                    'text' => 'Text',
                    'integer' => 'Integer',
                    'bigInteger' => 'Big Integer',
                    'float' => 'Float',
                    'decimal' => 'Decimal',
                    'boolean' => 'Boolean',
                    'date' => 'Date',
                    'dateTime' => 'DateTime',
                    'time' => 'Time',
                    'enum' => 'Enum'
                ],
                'string'
            );

            // Check for relationship field
            $relationshipData = null;
            if (str_ends_with($name, '_id') && in_array($type, ['integer', 'bigInteger'])) {
                $relatedModelLower = Str::beforeLast($name, '_id');
                $relatedModelPlural = Str::plural($relatedModelLower);
                $possibleApiEndpoint = $namespace 
                    ? "{$namespace}/{$relatedModelPlural}/list"
                    : "backend/" . Str::kebab($relatedModelPlural) . "/list";
                
                if ($this->confirm("This looks like a model Relationship. Do you want to load options for this field from an Api endpoint?", true)) {
                    $apiEndpoint = $this->ask('Please type in the Api endpoint to load data', $possibleApiEndpoint);
                    $labelColumn = $this->ask('Please type in the label column of the related model (e.g., name)', 'name');
                    
                    $relationshipData = [
                        'endpoint' => $apiEndpoint,
                        'labelColumn' => $labelColumn,
                        'relatedModel' => Str::studly(Str::singular($relatedModelLower)),
                        'relatedModelLower' => $relatedModelLower
                    ];
                }
            }

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
                'enumValues' => $enumValues,
                'relationshipData' => $relationshipData
            ];

        } while ($this->confirm("\nDo you want to add another column?", true));

        return $columns;
    }
} 