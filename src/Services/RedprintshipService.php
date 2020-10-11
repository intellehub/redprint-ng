<?php
namespace Shahnewaz\RedprintNg\Services;

use File;
use Schema;
use Storage;
use Artisan;
use Redprint;
use Carbon\Carbon;
use SplFileObject;
use Monolog\Logger;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\Filesystem;
use Shahnewaz\RedprintNg\Exceptions\BuildProcessException;

class RedprintshipService {
    /**
     * Request
     * */
    protected $request;

    /**
    * The filesystem instance.
    *
    * @var Filesystem
    */
    protected $files;

    /**
    * The Stubs default path.
    *
    * @var Filesystem
    */
    protected $stubsPath;
    /**
     * @var model
     * */
    protected $modelName;
    protected $pluralModelName;
    /**
     * @var controller
     * */
    protected $controllerName;
    protected $relationControllerName;
    /**
     * @var migration
     * */
    protected $migrationClass;

    /**
     * @var request
     * */
    protected $requestName;
    /**
     * @var model entity
     * */
    protected $modelEntity;
    /**
     * @var model entities
     * */
    protected $modelEntities;
    protected $modelEntitiesTable;
    protected $modelRequestName;
    protected $modelNamespace;

    /**
     * @var relation entity
     * */
    protected $relationModelName;
    protected $relationClassPlural;
    protected $relationEntity;
    protected $relationEntityField;
    protected $relationField;
    protected $relation;
    protected $targetTable;
    protected $targetColumn;

    protected $relationRequestName;
    protected $relationModelNamespace;

    /**
     * @var relation entities
     * */
    protected $relationEntities;

    /**
     * @var Laravels' Artisan Instance
     * */
    protected $artisan;

    /**
     * @var store process errors
     * */
    protected $errorsArray;
    /**
     * @var process informations
     * */
    protected $infoArray;

    /**
     * @var current operation ID and directory
     * */
    protected $currentOperationId;
    protected $operationDirectory;

    /**
     * Service construct
     * @param Request $request
     * */
    public function __construct()
    {
        $logger = new Logger('BuilderServiceLog');
        $logger->pushHandler(new StreamHandler(storage_path('BuilderService.log'), Logger::INFO));
        $this->logger = $logger;
        $this->files = new Filesystem;
        $this->errorsArray = [];
        $this->infoArray = [];
        $this->currentOperationId = Str::random(12);
        $this->operationDirectory = 'redprint/'.date('Y_m_d_his').'_'.$this->currentOperationId;
        $this->stubsPath = storage_path($this->operationDirectory.'/stubs');
    }

    /**
     * Store errors encountered by the process
     * @param String $string
     * @return bool
     * */
    private function error($string = null)
    {
        if (!is_null($string)) {
            $this->logger->addInfo($string);
        }
        return true;
    }

    /**
     * Store informations encountered by the process
     * @param String $string
     * @return bool
     * */
    private function info($string = null)
    {
        if (!is_null($string)) {
            $this->infoArray[] = $string;
        }
        return true;
    }

    /**
     * Get a list of current relationships
     * */
    public function getRelations()
    {
        $models = $this->getFiles('app', 'php', true, ['Http', 'Console', 'Providers', 'Traits', 'Exceptions']);
        $modelData = [];

        foreach ($models as $model) {
            // Get file data as array
            $fileData = file(base_path($model['full_path']));
            $modelName = $model['name'];
            $modelData[$modelName] = ['model' => $modelName, 'data' => $this->extractRelations($fileData, $model)];
        }

        return $modelData;
    }

    public function extractRelations($data)
    {
        $relationshipIdentifiers = Redprint::relationshipIdentifiers();
        $relationshipData = [];
        $matchPatern = $pattern = '#\((.*?)\)#';

        foreach ($data as $line) {
            foreach ($relationshipIdentifiers as $id) {
                if (strpos($line, $id)) {
                    if(preg_match_all($matchPatern, $line, $matches)) {
                        if (isset($matches)) {
                            $modelData = explode(',', $matches[1][0]);
                            $modelName = $this->stripString($modelData[0]);
                            $foreignKey = $this->stripString(isset($modelData[1]) ? $modelData[1] : strtolower($modelName).'_id');
                            $localKey = $this->stripString(isset($modelData[2]) ? $modelData[2] : 'id');
                        }
                        // May contain relationship table fields
                        $relationshipData[] = [
                            'type' => $id, 
                            'model' => $modelName, 
                            'foreign_key' => $foreignKey,
                            'local_key' => $localKey
                        ];
                    }
                }
            }
        }
        return collect($relationshipData);
    }

    /**
     * Strip strings from slashes, App, class and ::
     * */
    public function stripString($string)
    {
        // Take end string by exploding
        $parts = explode('\\', $string);
        $string = end($parts);

        $string = str_replace('App', '', $string);
        $string = str_replace("'", '', $string);
        $string = str_replace("\\", '', $string);
        $string = str_replace("::", '', $string);
        $string = str_replace("class", '', $string);
        return $string;
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string $path
     * @return string
     */
    protected function makeDirectory($path, $commit = false)
    {
        $permission = $commit ? 0755 : 0755;
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), $permission, true, true);
        }
    }

    /**
     * Write relationship methods into the Model
     * */
    public function writeRelation(Request $request)
    {
        $skippedWritingControllerLogics = 'Relationship added successfully.'
                                          .' However, we could not write additional controller'
                                          .' logics since the Controller was not found.';

        
        $model = $request->get('model');
        $with = $request->get('with');
        $this->relation = $request->get('relationship');


        $currentRelations = $this->getRelations();
        $currentExisting = array_key_exists($model, $currentRelations) ? $currentRelations[$model] : [];

        $message = 'Relationship added successfully.';
        $code = 201;

        if ($model === $with) {
            $message = 'Invalid relationship.';
            return ['code' => 422, 'message' => $message];
        }


        if (count($currentExisting)) {
            $currentRelationshipCollection = collect($currentExisting['data']);
            $exists = $currentRelationshipCollection
                        ->where('type', $this->relation)->where('model', $with)
                        ->count();
            if ($exists)  {
                $message = 'Relationship already exists.';
                return ['code' => 422, 'message' => $message];
            }
        }

        // Generate file names
        $this->modelName = Str::studly($request->get('model'));
        $this->relationModelName = Str::studly($request->get('with'));
        $this->pluralModelName = Str::plural($this->modelName);
        $this->modelEntity = Str::camel($request->get('model'));
        $this->modelEntities = Str::camel(Str::plural($this->modelName));
        $this->modelEntitiesTable = Str::snake($this->modelEntities);
        $this->relationEntity = Str::camel($this->relationModelName);
        $this->relationEntityField = Str::snake($this->relationEntity);
        $this->relationEntities = Str::camel(Str::plural($this->relationModelName));
        $this->relationClassPlural = Str::plural($this->relationModelName);
        
        // Get Namespace of both Models
        $this->modelNamespace = $this->getNamespace($this->modelName);
        $this->relationModelNamespace = $this->getNamespace($this->relationModelName);

        if ($this->relation === 'belongsTo') {
            $this->targetTable = $this->modelEntities;
            $this->targetColumn = $this->relationEntity.'_id';
        } else {
            $this->targetTable = $this->relationEntities;
            $this->targetColumn = $this->modelEntity.'_id';
        }

        $this->relationField = $this->relation === 'belongsTo' ?
                               $this->relationEntity.'_id' :
                               $this->modelEntity.'_id';

        $this->migrationClass = Str::studly('add_'.$this->relationField.'_to_'.$this->targetTable.'_table');
        
        $this->controllerName = $this->pluralModelName.'Controller';
        $this->relationControllerName = $this->relationClassPlural.'Controller';
        $this->modelRequestName = $this->modelName.'Request';
        $this->relationRequestName = $this->relationModelName.'Request';

        // dd($this);

        $this->makeDirectory(storage_path($this->operationDirectory.'/stubs'));
        $this->files->copyDirectory(__DIR__.'/../../stubs/', storage_path($this->operationDirectory.'/stubs'));
        $modelNamespaceFilePath = $this->modelNamespace ? $this->modelNamespace.'/'.$model.'.php' : $model.'.php';
        $this->makeDirectory(storage_path($this->operationDirectory.'/'.$modelNamespaceFilePath));
        $this->files->copy(app_path($modelNamespaceFilePath), storage_path($this->operationDirectory.'/'.$modelNamespaceFilePath));

        $modelFilePath = storage_path($this->operationDirectory.'/'.$modelNamespaceFilePath);


        $oneOnOne = ($this->relation === 'hasOne' || $this->relation === 'belongsTo');

        $stubPath = $oneOnOne ?
                    $this->stubsPath.'/relations/relationship_one_on_one.stub' :
                    $this->stubsPath.'/relations/relationship.stub';


        $relationNamespace = $this->relationModelNamespace ? $this->relationModelNamespace.'\\' : '';

        $stub = $this->files->get($stubPath);
        $stub = str_replace('{{WITH}}', $with, $stub);
        $stub = str_replace('{{MODEL}}', $model, $stub);
        $stub = str_replace('{{WITH_ENTITY}}', $this->relationEntity, $stub);
        $stub = str_replace('{{WITH_ENTITIES}}', $this->relationEntities, $stub);
        $stub = str_replace('{{MODEL_ENTITY}}', $this->modelEntity, $stub);
        $stub = str_replace('{{RELATIONSHIP}}', $this->relation, $stub);
        $stub = str_replace('{{RELATION_NAMESPACE}}',$relationNamespace, $stub);
        $stub = $stub."\n }";

        /* if ($this->relation === 'belongsTo') {
            $writeControllerLogic = $this->writeRelationshipMethodsInModelController();
            $this->makeRelationRequiredForFormRequest();
            $this->writeRelationshipSelectsInModelForm();
        } */

        if ($this->relation === 'belongsTo') {
            $this->writeMigration();
            Artisan::call('migrate');
        }

        $modelFile = file(app_path($modelNamespaceFilePath));
        // echo "<pre>"; print_r($modelFile); exit;
        $lastCurlyBrace = max(array_keys(preg_grep("~}~", $modelFile)));
        $modelFile[$lastCurlyBrace] = $stub;
        // echo "<pre>"; print_r($modelFile); exit;
        // echo app_path($modelNamespaceFilePath); exit;
        $this->files->put(app_path($modelNamespaceFilePath), $modelFile);
        $this->files->copy(
            app_path($modelNamespaceFilePath),
            storage_path($this->operationDirectory.'/'.$modelNamespaceFilePath)
        );

        // Try and run phpcs and phpcbf
        try {
            $generatedCommand = 'phpcbf '.base_path('app');
            $commandDirectory = base_path('vendor/bin');

            $processApp = $this->systemCommand($generatedCommand, $commandDirectory);
        } catch (\Exception $e) {
            // Silence!
        }


        return ['code' => $code, 'message' => $message];
    }

    /**
     * Decide Namespace used in a Model
     * */
    public function getNamespace($modelName)
    {
        // Get all models
        $modelFiles = collect($this->getFiles('app', 'php', true, ['Http', 'Console', 'Providers', 'Traits', 'Exceptions']));
        $modelData = $modelFiles->firstWhere('name', $modelName);
        return $modelData['path'];
    }

    /**
     * Make hasMany relation required in request file
     * */

    public function makeRelationRequiredForFormRequest()
    {
        $modelRequestFile = $this->modelNamespace ?
                            $this->modelNamespace.'/'.$this->modelRequestName.'.php' : 
                            $this->modelRequestName.'.php';
                            
        // Active request file path
        $requestFilePath = app_path('Http/Requests/Backend/'.$modelRequestFile);

        // Skip if the request file is not found.
        if(!$this->files->exists($requestFilePath)) {
            return false;
        }

        // Copy Request file
        $requestFileWritePath = storage_path(
            $this->operationDirectory.'/Http/Requests/Backend/'.$modelRequestFile
        );

        $this->makeDirectory($requestFileWritePath);
        $this->files->copy($requestFilePath, $requestFileWritePath);

        // Find desired line to write
        $requestFile = file($requestFileWritePath);
        $requestRuleBlockLine = min(array_keys(preg_grep("~return \[~", $requestFile))) + 1;

        // Prepare data
        $requestRuleBlockLineContent = $requestFile[$requestRuleBlockLine];
        $requestRuleBlockLineContent = $requestRuleBlockLineContent
                                        ."\t\t"."'{{RELATION_FIELD}}' => 'required|integer|exists:{{RELATION_ENTITIES}},id',"
                                        ."\n";
        $requestRuleBlockLineContent = $this->processStub($requestRuleBlockLineContent);

        // Write data
        $requestFile[$requestRuleBlockLine] = $requestRuleBlockLineContent;

        $written = $this->files->put($requestFileWritePath, $requestFile);
        $this->files->copy($requestFileWritePath, $requestFilePath);
        return true;

    }

    /**
     * Write relationship methods in Controller.
     */
    protected function writeRelationshipSelectsInModelForm()
    {
        // Active controller file path
        $formFilePath = resource_path('views/backend/'.$this->modelEntities.'/form.blade.php');

        // Skip if the request file is not found.
        if(!$this->files->exists($formFilePath)) {
            return false;
        }

        // Copy form view file
        $formFileWritePath = storage_path(
            $this->operationDirectory.'/resources/views/backend/'.$this->modelEntities.'/form.blade.php'
        );
        $this->makeDirectory($formFileWritePath);
        $this->files->copy($formFilePath, $formFileWritePath);

        $formFile = file($formFileWritePath);
        $formStartLine = "<input type=\"hidden\" name=\"id\"";
        $formStartLine = str_replace('{{MODEL_ENTITY}}', $this->modelEntity, $formStartLine);
        $pattern = "~".preg_quote($formStartLine)."~";
        $formStartLine = min(array_keys(preg_grep($pattern, $formFile)));

        // Write data
        $stub = $this->files->get($this->stubsPath . '/relations/views/formSelect.stub');
        $formStartLineContent = $formFile[$formStartLine];
        $formStartLineContent = $formStartLineContent."\n".$stub;
        $formStartLineContent = $this->processStub($formStartLineContent);


        $formFile[$formStartLine] = "\t\t".$formStartLineContent;


        $written = $this->files->put($formFileWritePath, $formFile);
        $this->files->copy($formFileWritePath, $formFilePath);
        return true;
    }

    /**
     * Write relationship methods in Controller.
     */
    protected function writeRelationshipMethodsInModelController()
    {
        $controllerNamespacedPath = $this->modelNamespace ?
                                    $this->modelNamespace.'/'.$this->controllerName.'.php' : 
                                    $this->controllerName.'.php';
        // Active controller file path
        $controllerFilePath = app_path('Http/Controllers/Backend/'.$controllerNamespacedPath);

        // Copy controller file
        $controllerFileWritePath = storage_path(
            $this->operationDirectory.'/Http/Controllers/Backend/'.$controllerNamespacedPath
        );

        // Skip if the file doesn't exist
        if(!$this->files->exists($controllerFilePath)) {
            return false;
        }

        $this->makeDirectory($controllerFileWritePath);
        $this->files->copy($controllerFilePath, $controllerFileWritePath);

        // Find line to write [form method]
        $controllerFile = file($controllerFileWritePath);
        $formReturnLine = "backend.{{MODEL_ENTITIES}}.form";
        $formReturnLine = str_replace('{{MODEL_ENTITIES}}', $this->modelEntities, $formReturnLine);
        $pattern = "~".preg_quote($formReturnLine)."~";
        $formReturnLine = min(array_keys(preg_grep($pattern, $controllerFile)));

        // Prepare data [form method]
        $modelTableName = strtolower(Str::snake($this->modelEntities));
        $relationTableName = strtolower(Str::snake($this->relationEntities));
        $columns = Schema::getColumnListing($relationTableName);
        $pluckable = "'".$columns[1]."', '".$columns[0]."'";
        // Write data
        $formReturnLineContent = $controllerFile[$formReturnLine];
        $formReturnLineContent = str_replace(';', '', $formReturnLineContent);
        $formReturnLineContent = trim($formReturnLineContent).'->with(\'{{RELATION_ENTITIES}}\', ${{RELATION_ENTITIES}});';
        $formReturnLineContent = "\t\t"
                                .'${{RELATION_ENTITIES}} = \\App\\{{RELATION_NAMESPACE}}{{RELATION_CLASS}}::pluck('.$pluckable.')->toArray();'
                                ."\n"
                                ."\t\t".$formReturnLineContent
                                ."\n";
        $formReturnLineContent = $this->processStub($formReturnLineContent);

        $controllerFile[$formReturnLine] = $formReturnLineContent;

        // Find line to write [post method]
        $postSaveLine = "{{MODEL_ENTITY}}->save()";
        $postSaveLine = str_replace('{{MODEL_ENTITY}}', $this->modelEntity, $postSaveLine);
        $pattern = "~".preg_quote($postSaveLine)."~";
        $postSaveLine = min(array_keys(preg_grep($pattern, $controllerFile)));
        // Prepare data [post method]
        $postSaveLineContent = $controllerFile[$postSaveLine];
        $postSaveLineContent = '${{MODEL_ENTITY}}->{{RELATION_FIELD}} = $request->get(\'{{RELATION_FIELD}}\');'
                                ."\n"
                                .$postSaveLineContent;
        $postSaveLineContent = $this->processStub($postSaveLineContent);

        $controllerFile[$postSaveLine] = $postSaveLineContent;

        $written = $this->files->put($controllerFileWritePath, $controllerFile);
        $this->files->copy($controllerFileWritePath, $controllerFilePath);
        return true;
    }

    /**
     * Write Migration file for relation
     * */
    public function writeMigration()
    {
        // Check if this model needs the migration
        $tableHasColumn = Schema::hasColumn($this->targetTable, $this->targetColumn);
        if ($tableHasColumn) {
            // Migration not needed
            return true;
        }
        $stub = $this->files->get($this->stubsPath.'/relations/database/migrations/add_field.stub');
        $stub = $this->processStub($stub);
        $targetFileName = date('Y_m_d_his').'_'.Str::snake($this->migrationClass).'.php';
        // Target to copy
        $targetPath = base_path('database/migrations/'.$targetFileName);
        // Write here
        $targetWritePath = storage_path($this->operationDirectory.'/database/migrations/'.$targetFileName);
        $this->makeDirectory($targetWritePath);
        $this->files->put($targetWritePath, $stub);
        $this->files->copy($targetWritePath, $targetPath);
        return true;
    }

   
    /**
     * @param string $stub
     * @return string
     * */
    public function processStub($stub)
    {
        $relationNamespace = $this->relationModelNamespace ? $this->relationModelNamespace.'\\' : '';
        $stub = str_replace('{{CONTROLLER_CLASS}}',$this->controllerName, $stub);
        $stub = str_replace('{{MODEL_CLASS}}',$this->modelName, $stub);
        $stub = str_replace('{{PLURAL_MODEL_NAME}}',$this->pluralModelName, $stub);
        $stub = str_replace('{{MODEL_ENTITY}}',$this->modelEntity, $stub);
        $stub = str_replace('{{MODEL_ENTITIES}}',$this->modelEntities, $stub);
        $stub = str_replace('{{MODEL_ENTITIES_TABLE}}',$this->modelEntitiesTable, $stub);
        $stub = str_replace('{{RELATION_ENTITY_FIELD}}',$this->relationEntityField, $stub);
        $stub = str_replace('{{RELATION_ENTITY}}',$this->relationEntity, $stub);
        $stub = str_replace('{{RELATION_ENTITIES}}',$this->relationEntities, $stub);
        $stub = str_replace('{{RELATION_NAMESPACE}}',$relationNamespace, $stub);
        $stub = str_replace('{{RELATION_CLASS}}',$this->relationModelName, $stub);
        $stub = str_replace('{{RELATION_CLASS_PLURAL}}',$this->relationClassPlural, $stub);
        $stub = str_replace('{{RELATION_FIELD}}',$this->relationField, $stub);
        $stub = str_replace('{{CAMEL_FIELD}}',Str::camel($this->relationField), $stub);
        $stub = str_replace('{{REQUEST_CLASS}}',$this->requestName, $stub);
        $stub = str_replace('{{MIGRATION_CLASS}}',$this->migrationClass, $stub);
        
        return $stub;
    }



    /**
     * Get a list of current files on a dir
     * @return  Array list of file names
     * */
    public function getFiles ($path, $lang, $recursive = false, $exclude = []) {
        
        if ($recursive) {
          $files = File::allFiles(base_path($path), $recursive);
        } else {
          $files = File::files(base_path($path), $recursive);
        }
        
        $filesData = [];

        foreach ($files as $file) {
            $shouldBeExcluded = false;
            foreach ($exclude as $excludedPath) {
              if ($shouldBeExcluded === false) {
                $shouldBeExcluded = (strpos($file->getRelativePath(), $excludedPath) !== false);
              }
            }
            if (!$shouldBeExcluded) {
              $filesData[] = [
                'name' =>  $file->getBasename('.php'),
                'path' => $file->getRelativePath(),
                'full_path' => $path.'/'.$file->getRelativePath().'/'.$file->getFilename()
              ];
            }
        }

        return $filesData;
    }


    /**
     * Run system commands
     * */
    public function systemCommand($cmd, $directory = null, $input = '')
    {
        $directory = $directory ?: base_path();
        $proc = proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $directory,
            NULL
        );
        
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stderr = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stdout = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        $return = proc_close($proc);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'return' => $return
        ];
    }

}
