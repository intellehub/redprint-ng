<?php

namespace Shahnewaz\RedprintNg\Traits;

use Illuminate\Support\Str;
use Shahnewaz\RedprintNg\Enums\DataTypes;

trait HandlesColumnInput
{
    private function getColumnInput(?string $namespace = null, $typePrompt = true, $detailsPrompt = true, $relationsPrompt = true): array
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

            $enumValues = [];
            $type = "string";
            $relationshipData = null;
            $nullable = true;
            $default = null;

            if($typePrompt) {
                $type = $this->choice(
                    'Select column type:',
                    DataTypes::getAvailableTypes(),
                    'string'
                );
                $this->info('Column type: ' . $type);

                // Additional prompts for enum type

                if ($type === 'enum') {
                    $enumValuesStr = $this->ask('Enter enum values (comma-separated)');
                    $enumValues = array_map('trim', explode(',', $enumValuesStr));
                }
            }

            // Check for relationship field
            if($relationsPrompt) {
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
            }

            if($detailsPrompt) {
                $nullable = $this->confirm('Is Nullable?', false);
                $default = $this->ask('Default value (press enter to skip)', null);
            }

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