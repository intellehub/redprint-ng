<?php
namespace Shahnewaz\Redprint\Services;

use File;
use Schema;
use Artisan;
use Redprint;
use Validator;
use Carbon\Carbon;
use SplFileObject;
use Monolog\Logger;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\Filesystem;
use Shahnewaz\Redprint\Database as RedprintDatabase;
use Shahnewaz\Redprint\Exceptions\BuildProcessException;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BuilEditService {
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
     * @var namespae
     * */
    protected $namespace;
    protected $namespacePath;
    protected $generalNamespace;
    protected $useNamespace;
    protected $routeNamespace;
    /**
     * @var model
     * */
    protected $modelName;
    protected $pluralModelName;
    /**
     * @var controller
     * */
    protected $controllerName;

    /**
     * @var migration
     * */
    protected $migrationFileName;

    /**
     * @var  $tableName
     * */
    protected  $tableName;

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
     * @var Files List to generate
     * */
    protected $filesList;

    protected $migrationSuffix;

    /**
     * Service construct
     * @param Request $request
     * */
    public function __construct(Request $request)
    {
        $logger = new Logger('BuilderServiceLog');
        $logger->pushHandler(new StreamHandler(storage_path('BuilderService.log'), Logger::INFO));
        $this->logger = $logger;
        $this->files = new Filesystem;
        $this->errorsArray = [];
        $this->infoArray = [];
        $this->request = $request;
        $this->currentOperationId = Str::random(12);
        $this->operationDirectory = 'redprint/'.$request->get('id');
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
            $this->logger->info($string);
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
     * Build a CRUD from request
     * @param Request $request
     * @return mixed
     * */
    public function editFromRequest()
    {
        $this->initialize();
        $this->validate();
        $this->cleanup();
        $this->crudJson();
        // Optimize
        $this->stubOptimizer();
        // $this->makeModel();

        $this->makeMigration();

        // All good! Copy files
        $this->copyGeneratedFiles();
        Artisan::call('migrate');
        $this->crudJson(true);
        return $this->modelEntities;
    }

    /**
    *Initialize filesystem and decide names for files
    * @return
    * */ 
    public function initialize () {

        $modelNameString = $this->request->get('model');
        $explodedModelNameString = explode('\\', $modelNameString);
        $namespaceSegmentsCount = count($explodedModelNameString);
        $modelNamePart = end($explodedModelNameString);
        
        $namespacePart = str_replace('\\'.$modelNamePart, '', $modelNameString);
        $namespacePart = str_replace('App\\', '', $namespacePart);
        $namespacePart = str_replace('App', '', $namespacePart);
        $explodedNamespacePart = explode('\\', $namespacePart);

        $namespaceString = 'App';

        if (count($explodedNamespacePart) >= 1) {
            $namespaceString = $namespaceString.'\\';
        }

        $lastNamespacePart = end($explodedNamespacePart);

        foreach ($explodedNamespacePart as $namespaceSegment) {
            if ($namespaceSegment !== $modelNamePart) {
                $namespaceString = $namespaceString.Str::studly($namespaceSegment);
                if ($namespaceSegment !== $lastNamespacePart) {
                    $namespaceString = $namespaceString.'\\';
                }
            }
        }

        $this->namespace = $namespaceString;
        $this->useNamespace = $this->namespace;

        if (count($explodedNamespacePart) >= 1) {
            $this->generalNamespace = str_replace('App\\', '', $this->namespace);
            $this->generalNamespace = str_replace('App', '', $this->namespace);
            $this->namespacePath = str_replace('\\', '/', $this->generalNamespace);
            if ($explodedNamespacePart[0] !== '' && $explodedNamespacePart[0] !== $modelNamePart) {
                $this->useNamespace = $this->useNamespace.'\\';
            }
        } else {
            $this->generalNamespace = '';
            $this->namespacePath = '';
        }

        $this->routeNamespace = $this->generalNamespace;

        if (strlen($this->generalNamespace) >= 1) {
            $this->routeNamespace = $this->routeNamespace.'\\';
        }

        if (strlen($this->namespacePath) >= 2) {
            $this->namespacePath = $this->namespacePath.'/';
        } 

        if ($this->routeNamespace[0] === '\\') {
            $this->routeNamespace = substr_replace($this->routeNamespace, '', 0, 1);
        }

        if ($this->generalNamespace === '\\') {
            $this->generalNamespace = '';
        }

        if ($this->routeNamespace === '\\') {
            $this->routeNamespace = '';
        }

        if ($this->namespace === 'App\\') {
            $this->namespace = 'App';
        }

        
        $this->modelName = Str::studly($modelNamePart);
        $this->pluralModelName = Str::plural($this->modelName);
        $this->modelEntity = Str::camel($this->modelName);
        $this->modelEntities = Str::camel(Str::plural($this->modelName));
        $this->controllerName = $this->pluralModelName.'Controller';
        $this->requestName = $this->modelName.'Request';
        $this->tableName = Str::snake(Str::plural($this->modelName));
        $this->migrationSuffix = time();
        $this->migrationFileName = date('Y_m_d_his').'_modify_'.$this->tableName.'_table_'.$this->migrationSuffix;


        $this->makeDirectory(storage_path($this->operationDirectory.'/stubs'));
        $this->makeDirectory(storage_path($this->operationDirectory.'/backup'));
        $this->files->copyDirectory(__DIR__.'/../../stubs/database/migrations/', storage_path($this->operationDirectory.'/stubs/database/migrations'));
        $this->prepareFileList();
    }

    public function prepareFileList()
    {
        $webFiles = [
            ['path' => 'database/migrations/'.$this->migrationFileName.'.php', 'conflict' => true],

        ];

        $apiFiles = [];


         $this->filesList = array_merge($webFiles, $apiFiles);
         return true;
    }

    /**
     * JSON File Describing the CRUD content and files
     * */
    public function crudJson($finalize = false)
    {
        $filePath = storage_path($this->operationDirectory. '/crud.json');
        $targetPath = storage_path($this->operationDirectory. '/crud.json');
        $this->makeDirectory($targetPath);
        
        $stub = $this->files->get($filePath);

        if ($finalize) {
            $stub = str_replace('"successful" : false', '"successful" : true', $stub);
            $this->files->put($filePath, $stub);
            $this->files->copy($filePath, $targetPath);
            return true;
        }
        
        $stub = str_replace('"{{REVISIONS}}"', json_encode($this->request->all()).","."\n".'"{{REVISIONS}}"', $stub);

        $this->files->put($filePath, $stub);
        $this->files->copy($filePath, $targetPath);
        return true;
    }

    /**
     * Get a list of current migration files
     * @return  Array list of migration file names
     * */
    public function getMigrationFiles () {
        $migrations = File::files(base_path('database/migrations/'));
        $migrationData = [];

        foreach ($migrations as $migration) {
            $migrationData[] = $migration->getBasename('.php');
        }

        return $migrationData;
    }

    /**
     * Check if a migration file already exists
     * @return  bool
    **/
    public function migrationExists ($fileName) {
        $migrationFiles = $this->getMigrationFiles();
        $exists = false;
        foreach ($migrationFiles as $file) {
            if (strpos($file, $fileName)) {
                $exists = true;
            }
        }

        return $exists;
    }

    /**
     * Check if table already exists in Schema
     * @return  bool
    **/
    public function tableExists () {
        return Schema::hasTable($this->modelEntities);
    }

    /**
     * Validate this operation
     * @return  mixed
     * */
    public function validate () {

        $modelFilePath = 'app/';
        $controllerPath = 'app/Http/Controllers/Backend/';
        $requestPath = 'app/Http/Requests/Backend/';

        $namespacePath = $this->namespacePath;
        if ($this->namespace !== '') {

            $modelFilePath = $modelFilePath.$namespacePath;
            $controllerPath = $controllerPath.$namespacePath;
            $requestPath = $requestPath.$namespacePath;
        }

        $tableExists = $this->tableExists();

        $migrationStatements = $this->request->get('migration');
        $oldMigrations = $this->request->get('old_migrations');
        
        $validator = Validator::make($this->request->all(), []);

        $that = $this;

        $validator->after(function($validator) 
            use (
                $tableExists, 
                $migrationStatements,
                $oldMigrations,
                $that
            ) {

            if (count($migrationStatements) === 0) {
                $validator->errors()->add('Migration', 'No migrations to process.');
            }

            if (strlen($that->modelName) < 2 || strlen($that->modelName) > 50) {
                $validator->errors()->add('Model', 'Model name must be between 2 and 50 characters.');
            }

            if (preg_match('/[\'^£$%&*()}{@#~?><>.,|=_+¬-]/', $that->modelName)){
                $validator->errors()->add('Model', 'Model name contains invalid characters. It should only contain letters and numbers.');
            }

            if (
                preg_match('/^\d/', $that->modelName) === 1
            ) {
                $validator->errors()->add('Model', 'Model name cannot start with a number.');
            }


            if (!$tableExists) {
                $validator->errors()->add('Table', 'Table <code>'.$this->modelEntities.'</code> does not exist!');
            }

            $migrationTableColumnNamePattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

            $atLeastOneFieldInForm = false;

            $oldFields = [];
            foreach ($oldMigrations as $oldMigration) {
                $oldFields[] = $oldMigration['field_name'];
            }

            // Validate migration statements
            foreach ($migrationStatements as $migration) {

                $dataType = $migration['data_type'];
                $fieldName = $migration['field_name'];
                $nullable = $migration['nullable'];
                $default = $migration['default'];
                $index = $migration['index'];
                $unique = $migration['unique'];
                $showIndex = $migration['show_index'];
                $canSearch = $migration['can_search'];
                $isFile = $migration['is_file'];
                $fileType = $migration['file_type'];
                $inForm = $migration['in_form'];

                $numericDatatype = Redprint::numericTypes();
                $incrementTypes = Redprint::incrementTypes();

                if (in_array($fieldName, $oldFields)) {
                    $validator->errors()->add('Migration', 'Field <code>'.$fieldName.'</code> already exists.');
                }

                if ($inForm) {
                    $atLeastOneFieldInForm = true;
                }

                if ($dataType === 'enum' && strlen($default) === 0) {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of type ENUM. It must have default value set.');
                }

                if (in_array($dataType, $incrementTypes) && $default !== null) {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of type Increments. It does not require or allow a default value. Please leave it blank.');
                }

                if ($dataType === 'boolean' && intval($default) > 1) {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of type Boolean. It must have default value set between 0 and 1.');
                }

                if ($dataType === 'tinyInteger' && intval($default) > 9) {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of type Tiny Integer. It must have default value set between 0 and 9.');
                }

                if (
                    $default && (in_array($dataType, $numericDatatype) && !is_numeric($default))
                ) {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of Numeric type. It must have a proper default value set.');
                }

                if (!$fieldName) {
                    $validator->errors()->add('Migration', 'Migration statement must have a field name.');
                }

                if ($isFile && $dataType !== 'string') {
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is of type FILE. It must have a data type String.');
                }

                if (preg_match('/[\'^£$%&*()}{@#~?><>.,|=+¬-]/', $fieldName)){
                    $validator->errors()->add('Migration', 'Field <code>'.$fieldName.'</code> name contains invalid characters. It should only contain letters and numbers.');
                }

                if (preg_match('/[\'^£$%&*()}{@#~?><>|=_+¬-]/', $default)){
                    $validator->errors()->add('Migration', 'Field <code>'.$fieldName.'</code> default value contains illegal characters.');
                }

                if ($dataType === 'increments' && $fieldName !== 'id') {
                    $validator->errors()->add('Migration', 'Only <code>id</code> field can have <code>increments</code> data type. Please change data type of <code>'.$fieldName.'</code> to something else.');
                }

                if(!preg_match($migrationTableColumnNamePattern, $fieldName)){
                    $validator->errors()->add('Migration', 'Migration statement for field <code>'.$fieldName.'</code> is invalid. The field name must start with letters and must not contain special characters.');
                }
                
            }

        });

        $validator->validate();
        return true;
    }

    /**
     * Do a general cleanup for autoloads and compiled class names
     * @return
     * */
    public function cleanup ($phpcbf = false) {

        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        if ($phpcbf === true) {
            // Try and run phpcs and phpcbf
            try {
                $commandDir = base_path('vendor/bin');
                $generatedCommandForApp = 'phpcbf '.base_path('app');
                $generatedCommandForRoutes = 'phpcbf '.base_path('routes');

                $outputAppCommand = $this->systemCommand($generatedCommandForApp, $commandDir);
                $outputRoutesCommand = $this->systemCommand($generatedCommandForRoutes, $commandDir);
            } catch (\Exception $e) {
                // Silence!
            }
        }

        // Dump Composer autoload
        try {
            $process = $this->systemCommand('composer dump-autoload');
        } catch (\Exception $e) {
            // Silence!
        }

    }


    /**
     * Make migration
     * */
    public function makeMigration()
    {
        $this->writeFile($this->migrationFileName, 'migration');
    }

    /**
     * Write file
     *
     * @param string $name
     * @param string $intent
     * */    
    protected function writeFile($name, $intent, $commit = false, $frontend = false)
    {
        $content = '';
        $basePath = storage_path($this->operationDirectory);

        $baseDirectoryName = $frontend ? 'Frontend' : 'Backend';

        switch ($intent) {

            case 'migration':
                $filePath = $basePath.'/database/migrations/'.$this->migrationFileName.'.php';
                $content = $this->getMigrationStub($name);
                break;

            default:
                $filePath = $basePath.$filePath;
                break;
        }

        if ($this->files->exists($filePath)) {
            return $this->error($intent.' already exists!');
        }

        $this->makeDirectory($filePath);
        $this->files->put($filePath, $content);
        $this->info($intent.' for: '.$this->modelName.' created successfully');
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string $path
     * @return string
     */
    protected function makeDirectory ($path, $commit = false) {
        $permission = $commit ? 0775 : 0775;
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), $permission, true, true);
        }
    }
    
    /**
     * Compose the migration file stub.
     *
     * @return string
     */
    protected function getMigrationStub($fileName)
    {
        $stub = $this->files->get($this->stubsPath . '/database/migrations/migration_edit.stub');
        return $this->processStub($stub);
    }


    /**
     * @param string $stub
     * @return string
     * */
    public function processStub ($stub)
    {

        $baseModelAs = $this->modelName === 'Model' ? 'Model as BaseModel' : 'Model';
        $baseModel = $this->modelName === 'Model' ? 'BaseModel' : 'Model';
        $softDeletesAs = $this->modelName === 'SoftDelete' ? 'SoftDeletes as BaseSoftDeletes' : 'SoftDeletes';
        $baseSoftDeletes = $this->modelName === 'SoftDelete' ? 'BaseSoftDeletes' : 'SoftDeletes';

        $stub = str_replace('{{CONTROLLER_CLASS}}',$this->controllerName, $stub);
        $stub = str_replace('{{NAMESPACE}}',$this->namespace, $stub);
        $stub = str_replace('{{GENERAL_NAMESPACE}}',$this->generalNamespace, $stub);
        $stub = str_replace('{{USE_NAMESPACE}}',$this->useNamespace, $stub);
        $stub = str_replace('{{ROUTE_NAMESPACE}}',$this->routeNamespace, $stub);
        $stub = str_replace('{{MODEL_CLASS}}',$this->modelName, $stub);
        $stub = str_replace('{{PLURAL_MODEL_NAME}}',$this->pluralModelName, $stub);
        $stub = str_replace('{{MODEL_ENTITY}}',$this->modelEntity, $stub);
        $stub = str_replace('{{MODEL_ENTITIES}}',$this->modelEntities, $stub);
        $stub = str_replace('{{REQUEST_CLASS}}',$this->requestName, $stub);
        $stub = str_replace('{{TABLE_NAME}}',$this->tableName, $stub);


        $stub = str_replace('{{BASE_MODEL}}', $baseModel, $stub);
        $stub = str_replace('{{BASE_MODEL_AS}}', $baseModelAs, $stub);
        
        $stub = str_replace('{{BASE_SOFTDELETES}}', $baseSoftDeletes, $stub);
        $stub = str_replace('{{SOFTDELETES_AS}}', $softDeletesAs, $stub);
        $stub = str_replace('{{MIGRATION_SUFFIX}}', $this->migrationSuffix, $stub);


        $protectedTableNameStatement = 'protected $table = \''.$this->tableName.'\';';
        $stub = str_replace('//PROTECTED_TABLE_NAME', $protectedTableNameStatement, $stub);
        return $stub;
    }

    /**
     * Copy generated files
     * */
    public function copy($file, $conflict = true)
    {
        $source = storage_path($this->operationDirectory.'/'.$file);
        $destination = base_path($file);

        if ($this->files->exists($destination) && $conflict) {
            throw new BuildProcessException('File already exists: '.$destination.'. Please delete all related files or try restore function if you want to roll back an operation.');
        }

        if ($this->files->exists($source)) {
            $this->makeDirectory($destination);
            $this->files->copy($source, $destination);
        }
    }

    /**
     * Copy generated files
     * @return void
     * */
    public function copyGeneratedFiles()
    {
        foreach ($this->filesList as $file) {
            $this->copy($file['path'], $file['conflict']);
        }
        $this->cleanup(true);
    }

    /**
     * Get Line position of an Splfileobject Object by an identifier string
     * @return signed int
     * */
    public function getFileLineByIdentifier($file, $identifier)
    {
        $desiredLine = -10;
        foreach ($file as $lineNumber => $lineContent) {
            if (FALSE !== strpos($lineContent, $identifier)) {
                $desiredLine = $lineNumber; // zero-based
                break;
            }
        }
        return $desiredLine;
    }

    /**
     * Optimize file stubs
     * */
    public function stubOptimizer()
    {

        $softdeletes = $this->request->get('softdeletes');
        $timeDataTypes = Redprint::timeTypes();
        $numericDatatypes = Redprint::numericTypes();
        $integerTypes = Redprint::integerTypes();
        $incrementTypes = Redprint::incrementTypes();
        $longTextDataTypes = Redprint::longTextDataTypes();

        $migrationStubFilePath = $this->stubsPath.'/database/migrations/migration_edit.stub';
        $migrationStub = $this->files->get($migrationStubFilePath);

        // Write migration lines
        $migrationStatements = $this->request->get('migration');

        $migrationLines = '';
        $migrationDropLines = '';

        // Prepare stub
        foreach ($migrationStatements as $line) {
            $dataType = $line['data_type'];
            $fieldName = $line['field_name'];
            $col_xs = $line['col_xs'];
            $col_md = $line['col_md'];
            $col_lg = $line['col_lg'];
            $nullable = $line['nullable'];

            // Not letting users set a default value for date fields and enum. Fault prone
            $default = (in_array($dataType, $timeDataTypes) || in_array($dataType, $incrementTypes) || $dataType === 'enum') ? '' : $line['default'];

            // Cast default values properly
            if (in_array($dataType, $numericDatatypes)) {
                $default = floatval($default);
            }
            if (in_array($dataType, $integerTypes)) {
                $default = intval($default);
            }

            $dataTypeParameter = ($dataType === 'enum') ? $line['default'] : null;
            $index = $line['index'];
            $unique = $line['unique'];
            $showIndex = $line['show_index'];
            $canSearch = $line['can_search'];
            $isFile = $line['is_file'];
            $fileType = $line['file_type'];
            $inForm = $line['in_form'];

            // Process $dataTypeParameter
            if ($dataTypeParameter) {
                $sanitizedDataTypeParameter = '[';
                $params = explode(',', $line['default']);
                foreach ($params as $param) {
                    $endingComma = ($param !== end($params)) ? "," : '';
                    $param = str_replace("'", '', $param);
                    $param = str_replace('"', '', $param);
                    $sanitizedDataTypeParameter = $sanitizedDataTypeParameter."'".trim($param)."'".$endingComma;
                }
                $sanitizedDataTypeParameter = $sanitizedDataTypeParameter.']'; 
                $dataTypeParameter = $sanitizedDataTypeParameter;
            }
            $dataTypeParameterPreceedingComma = $dataTypeParameter ? ', ' : '';

            $statement = '$table->'."{TYPE}('{FIELD}'{PARAM})";
            /**
             * Migration optimization
             * */
            $statement = str_replace('{TYPE}', $dataType, $statement);
            $statement = str_replace('{FIELD}', $fieldName, $statement);
            $statement = str_replace('{PARAM}', $dataTypeParameterPreceedingComma.$dataTypeParameter, $statement);


            if ($nullable) {
                $statement = $statement.'->nullable()';
            }

            if ($index) {
                $statement = $statement.'->index()';
            }

            if ($unique) {
                $statement = $statement.'->unique()';
            }

            if ($default) {
                $defaultVal = is_numeric($default) ? $default : "'".$default."'";
                $statement = $statement.'->default('.$defaultVal.')';
            }


            $dropStatement = '$table->'."dropColumn('{FIELD}')";
            $dropStatement = str_replace('{FIELD}', $fieldName, $dropStatement);

            $migrationLines = $migrationLines.$statement.';'."\n"."\t\t\t";
            $migrationDropLines = $migrationDropLines.$dropStatement.';'."\n"."\t\t\t";


        }

        // Optimize and rewrite Stubs
        // Migration
        $migrationStub = str_replace('//MIGRATION_LINES', $migrationLines, $migrationStub);
        $migrationStub = str_replace('//MIGRATION_DROP_LINES', $migrationDropLines, $migrationStub);
        $this->files->put($migrationStubFilePath, $migrationStub);
        
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
