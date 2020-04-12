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

class BuilderService {
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
     * Build a CRUD from request
     * @param Request $request
     * @return mixed
     * */
    public function buildFromRequest()
    {
        $this->initialize();
        $this->validate();
        $this->cleanup();
        $this->crudJson();
        // Optimize
        $this->stubOptimizer();
        $this->makeModel();

        $this->makeMigration();

        // WEB
        $this->makeWebRoutes();
        $this->makeWebRoutes(true); // Frontend
        $this->makeController();
        $this->makeController(true); // Frontend
        $this->makeRequest();
        $this->makeViewIndex();
        $this->makeViewIndex(true); // Frontend
        $this->makeViewForm();
        $this->buildMenu();
        // $this->makeRoutes();

        // API
        $this->makeApiRoutes();
        $this->makeApiController();
        $this->makeModelResource();
        $this->makeModelResourceCollection();
        
        
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
        $this->migrationFileName = date('Y_m_d_his').'_create_'.$this->tableName.'_table';

        /* Debug Code 
        $resourceCollection = [
            'namespace' => $this->namespace,
            'generalNamespace' => $this->generalNamespace,
            'useNamespace' => $this->useNamespace,
            'routeNamespace' => $this->routeNamespace,
            'namespacePath' => $this->namespacePath,
            'modelName' => $this->modelName,
            'pluralModelName' => $this->pluralModelName,
            'modelEntity' => $this->modelEntity,
            'modelEntities' => $this->modelEntities,
            'controllerName' => $this->controllerName,
            'requestName' => $this->requestName,
            'tableName' => $this->tableName,
            'migrationFileName' => $this->migrationFileName
        ];

        echo "<pre>"; print_r($resourceCollection); exit;
        */

        $this->makeDirectory(storage_path($this->operationDirectory.'/stubs'));
        $this->makeDirectory(storage_path($this->operationDirectory.'/backup'));
        $this->files->copyDirectory(__DIR__.'/../../stubs/', storage_path($this->operationDirectory.'/stubs'));
        $this->prepareFileList();
    }

    public function prepareFileList()
    {
        $webFiles = [
            ['path' => 'routes/web.php', 'conflict' => false],
            ['path' => 'routes/api.php', 'conflict' => false],
            ['path' => 'config/backend_menu.php', 'conflict' => false],
            ['path' => 'app/'.$this->namespacePath.$this->modelName.'.php', 'conflict' => true],
            ['path' => 'database/migrations/'.$this->migrationFileName.'.php', 'conflict' => true],
            ['path' => 'app/Http/Requests/Backend/'.$this->namespacePath.$this->requestName.'.php', 'conflict' => true],
            ['path' => 'app/Http/Controllers/Backend/'.$this->namespacePath.$this->controllerName.'.php', 'conflict' => true],
            ['path' => 'app/Http/Controllers/Frontend/'.$this->namespacePath.$this->controllerName.'.php', 'conflict' => true],
            ['path' => 'resources/views/backend/'.$this->modelEntities.'/index.blade.php', 'conflict' => true],
            ['path' => 'resources/views/backend/'.$this->modelEntities.'/form.blade.php', 'conflict' => true],
            ['path' => 'resources/views/frontend/'.$this->modelEntities.'/index.blade.php', 'conflict' => true],

        ];

        $apiFiles = [];

        if ($this->request->get('api_code')) {
            $apiFiles = [
                [
                    'path' => 'app/Http/Controllers/Backend/API/'.$this->namespacePath.$this->controllerName.'.php',
                    'conflict' => true
                ],
                [
                    'path' => 'app/Http/Resources/'.$this->namespacePath.$this->modelName.'.php',
                    'conflict' => true
                ],
                [
                    'path' => 'app/Http/Resources/'.$this->namespacePath.$this->modelName.'Collection.php',
                    'conflict' => true
                ],
            ];
        }

         $this->filesList = array_merge($webFiles, $apiFiles);
         return true;
    }

    /**
     * JSON File Describing the CRUD content and files
     * */
    public function crudJson($finalize = false)
    {
        $filePath = $this->stubsPath . '/crud.stub';
        $targetPath = storage_path($this->operationDirectory. '/crud.json');
        $this->makeDirectory($targetPath);
        
        $stub = $this->files->get($filePath);

        if ($finalize) {
            $stub = str_replace('"successful" : false', '"successful" : true', $stub);
            $this->files->put($filePath, $stub);
            $this->files->copy($filePath, $targetPath);
            return true;
        }
        
        $fileLines = '';
        foreach ($this->filesList as $file) {
            $lastItem = end($this->filesList);
            $comma = ($file['path'] === $lastItem['path']) ? '' : ',';
            $conflict = $file['conflict'] ? 'true': 'false';
            $fileJson = '{ "path" : "'.$file['path'].'", "conflict" : "'.$conflict.'" }'.$comma;
            $fileLines = $fileLines.$fileJson."\n"."\t\t";
        }
        
        $stub = str_replace('{{CRUD_ID}}', $this->operationDirectory, $stub);
        $stub = str_replace('{{MODEL_CLASS}}', $this->modelName, $stub);
        $stub = str_replace('{{MIGRATION_FILE_NAME}}', $this->migrationFileName, $stub);
        $stub = str_replace('//FILE_LINES', $fileLines, $stub);

        $stub = str_replace('"{{REQUEST_LINES}}"', json_encode($this->request->all()), $stub);

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

        $modelFileExists = $this->files->exists(base_path($modelFilePath.$this->modelName.'.php'));
        $controllerFileExists = $this->files->exists(base_path($controllerPath.$this->controllerName.'.php'));
        $requestFileExists = $this->files->exists(base_path($requestPath.$this->requestName.'.php'));

        $migrationFileBaseName = '_create_'.Str::snake(Str::plural($this->modelName)).'_table';
        $migrationFileExists = $this->migrationExists($migrationFileBaseName);

        $tableExists = $this->tableExists();

        $indexFileExists = $this->files->exists(base_path('resources/views/backend/'.$this->modelEntities.'/index.blade.php'));
        $formFileExists = $this->files->exists(base_path('resources/views/backend/'.$this->modelEntities.'/form.blade.php'));
        $frontendIndexFileExists = $this->files->exists(base_path('resources/views/frontend/pages/'.$this->modelEntities.'/index.blade.php'));


        $migrationStatements = $this->request->get('migration');

        
        $validator = Validator::make($this->request->all(), []);

        $that = $this;

        $validator->after(function($validator) 
            use (
                $modelFileExists, 
                $controllerFileExists, 
                $requestFileExists, 
                $migrationFileExists, 
                $indexFileExists,
                $frontendIndexFileExists,
                $formFileExists, 
                $tableExists, 
                $migrationStatements,
                $that
            ) {

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

            if ($modelFileExists) {
                $validator->errors()->add('Model', 'Model file with name <code>'.$this->modelName.'.php</code> already exists!');
            }

            if ($controllerFileExists) {
                $validator->errors()->add('Controller', 'Controller file with name <code>'.$this->controllerName.'.php</code> already exists!');
            }

            if ($requestFileExists) {
                $validator->errors()->add('Request', 'Request file with name <code>'.$this->requestName.'.php</code> already exists!');
            }

            if ($migrationFileExists) {
                $validator->errors()->add('Migration', 'Migration file to create <code>'.$this->modelEntities.'</code> table already exists!');
            }

            if ($indexFileExists) {
                $validator->errors()->add('View Index', 'View index file with name <code>index.blade.php</code> already exists!');
            }

            if ($frontendIndexFileExists) {
                $validator->errors()->add('View Frontend', 'Frontend view index file with name <code>index.blade.php</code> already exists!');
            }

            if ($formFileExists) {
                $validator->errors()->add('View Form', 'View form file with name <code>form.blade.php</code> already exists!');
            }

            if ($tableExists) {
                $validator->errors()->add('Table', 'Table <code>'.$this->modelEntities.'</code> already exists!');
            }

            $migrationTableColumnNamePattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

            $atLeastOneFieldInForm = false;

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

            if ($atLeastOneFieldInForm === false) {
                $validator->errors()->add('Migration', 'At least one field should exist in the form. Please check <code>In Form</code> for at least one field below.');
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
     * Make a Model
     * */
    public function makeModel()
    {
        return $this->writeFile($this->modelName, 'model');
    }


    /**
     * Make Model Resource
     * */
    public function makeModelResource()
    {
        return $this->writeFile($this->modelName, 'resource');
    }

    /**
     * Make Model Resource
     * */
    public function makeModelResourceCollection()
    {
        return $this->writeFile($this->modelName, 'resource_collection');
    }


    /**
     * Make migration
     * */
    public function makeMigration()
    {
        $this->writeFile($this->migrationFileName, 'migration');
    }

    /**
     * Generate Controller
     */
    protected function makeController($frontend = false)
    {
        return $this->writeFile($this->controllerName, 'controller', false, $frontend);
    }

    /**
     * Generate API Controller
     */
    protected function makeApiController()
    {
        if ($this->request->get('api_code')) {
            return $this->writeFile($this->controllerName, 'api_controller');
        }
    }

    /**
     * Generate Form validation Request.
     */
    protected function makeRequest()
    {
        return $this->writeFile($this->requestName,'request');
    }    
    
    /**
     * Generate index view file.
     */
    protected function makeViewIndex($frontend = false)
    {
        return $this->writeFile('index', 'view', false, $frontend);
    }

    /**
     * Generate Form.
     */
    protected function makeViewForm()
    {
        return $this->writeFile('form','view');
    }


    /**
     * Make Routes.
     */
    protected function makeWebRoutes($frontend = false)
    {
        return $this->makeRoutes('web', $frontend);
    }


    /**
     * Make API Routes.
     */
    protected function makeApiRoutes()
    {
        if ($this->request->get('api_code')) {
            return $this->makeRoutes('api');
        }
        return false;
    }

    /**
     * Make Routes.
     */
    protected function makeRoutes($type = 'web', $frontend = false)
    {
        $fresh = false;
        $basePath = $type ==='web' ? 'routes/web.php' : 'routes/api.php';
        // Active routes file path
        $routesFilePath = base_path($basePath);
        // Copy routes file
        $routesFileWritePath = storage_path($this->operationDirectory.'/'.$basePath);

        if (!file_exists($routesFileWritePath)) {
            $fresh = true;
            $this->makeDirectory($routesFileWritePath);
            $this->files->copy($routesFilePath, $routesFileWritePath);       
        }

        $routeFile = file($routesFileWritePath);
        // Write after the first closing brace
        // Does this file have a route group ?
        $routeGroupRegex = $frontend ?
                            "/Route::namespace\('Frontend'\)->prefix\('pages'\)->group/" :
                            "/Route::middleware\(\['role:admin', 'auth'\]\)->namespace\('Backend'\)->prefix\('backend'\)->group/";

        if ($type === 'api') {
            $routeGroupRegex = "/Route::middleware\(\['jwt\.auth', 'role:admin'\]\)->namespace\('Backend\\\API'\)->prefix\('v1\/backend'\)->group/";
        }

        $hasRouteGroup = preg_grep("$routeGroupRegex", $routeFile);
        $lastClosingBrace = $hasRouteGroup ? (max(array_keys(preg_grep("$routeGroupRegex", $routeFile))) + 1) : max(array_keys($routeFile));

        // Write data
        $stub = $this->getRoutesStub($type, $frontend);

        if ($fresh === true) {
            // Take a backup of route content
            $backupPath = str_replace('.php', '.php.bak', $routesFileWritePath);
            $this->makeDirectory($backupPath);
            $this->files->copy($routesFileWritePath, $backupPath);
            $this->files->put($backupPath, $stub);
        }

        $routeFile[$lastClosingBrace] = $routeFile[$lastClosingBrace]."\n".$stub."\n";
        $written = $this->files->put($routesFileWritePath, $routeFile);
        return $written;
    }

    public function buildMenu()
    {
        $menuItem = [
            'text'        => Str::title($this->modelEntities),
            'route'       => $this->modelEntity.'.index',
            'icon'        => $this->request->get('icon') ?: 'icon-folder',
        ];
        $currentMenu = config('backend_menu');
        
        $builtMenu = $currentMenu;
        $builtMenu[] = $menuItem;
        $builtMenu = var_export(array_values($builtMenu), true);

        $content = $this->files->get($this->stubsPath . '/config/backend_menu.php');
        $content = str_replace('//MENU', $builtMenu, $content);
        $content = str_replace('array', '', $content);
        $content = str_replace('(', '[', $content);
        $content = str_replace(')', ']', $content);

        $filePath = storage_path($this->operationDirectory.'/config/backend_menu.php');

        $this->makeDirectory($filePath);
        $this->files->put($filePath, $content);
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
            case 'controller':
                $filePath = $basePath.'/app/Http/Controllers/'.$baseDirectoryName.'/'.$this->namespacePath.$name.'.php';
                $content = $this->getControllerStub(true, $frontend);
                break;
            case 'api_controller':
                $filePath = $basePath.'/app/Http/Controllers/Backend/API/'.$this->namespacePath.$name.'.php';
                $content = $this->getControllerStub(false);
                break;            
            case 'request':
                $filePath = $basePath.'/app/Http/Requests/Backend/'.$this->namespacePath.$name.'.php';
                $content = $this->getRequestStub();
                break;

            case 'view':
                $filePath = $basePath.'/resources/views/'.strtolower($baseDirectoryName).'/'.$this->modelEntities.'/'.$name.'.blade.php';
                $content = $this->getViewStub($name, $frontend);
                break;

            case 'model':
                $filePath = $basePath.'/app/'.$this->namespacePath.$this->modelName.'.php';
                $content = $this->getModelStub($name);
                break;

            case 'resource':
                $filePath = $basePath.'/app/Http/Resources/'.$this->namespacePath.$this->modelName.'.php';
                $content = $this->getModelResourceStub($name);
                break;

            case 'resource_collection':
                $filePath = $basePath.'/app/Http/Resources/'.$this->namespacePath.$this->modelName.'Collection.php';
                $content = $this->getModelResourceCollectionStub($name);
                break;

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
     * Compose the Model file stub.
     *
     * @return string
     */
    protected function getModelStub($fileName)
    {
        $modelStub = $this->request->get('softdeletes') ? 'model_with_softdeletes.stub' : 'model.stub';
        $stub = $this->files->get($this->stubsPath . '/models/'.$modelStub);
        return $this->processStub($stub);
    }


    /**
     * Compose the Model Resource file stub.
     *
     * @return string
     */
    protected function getModelResourceStub($fileName)
    {
        $stub = $this->files->get($this->stubsPath . '/resources/model.stub');
        return $this->processStub($stub);
    }

    /**
     * Compose the Model Resource Collection file stub.
     *
     * @return string
     */
    protected function getModelResourceCollectionStub($fileName)
    {
        if(!$this->request->get('api_code')) { return false; }
        $stub = $this->files->get($this->stubsPath . '/resources/model_collection.stub');
        return $this->processStub($stub);
    }


    /**
     * Compose the migration file stub.
     *
     * @return string
     */
    protected function getMigrationStub($fileName)
    {
        $stub = $this->files->get($this->stubsPath . '/database/migrations/migration.stub');
        return $this->processStub($stub);
    }


    /**
     * Compose widget file stub.
     *
     * @return string
     */
    protected function getControllerStub($web = true, $frontend = false)
    {
        $prefix = '';
        $prefix = (!$web) ? 'api_' : '';
        $prefix = $frontend ? $prefix.'frontend_' : $prefix;
        // Don't generate api controllers if not requested
        if (!$web && !$this->request->get('api_code')) {
            return false;
        }
        $controllerStub = ($this->request->get('softdeletes') && !$frontend) ?
                        $prefix.'controller_with_softdeletes.stub' :
                        $prefix.'controller.stub';
        $stub = $this->files->get($this->stubsPath . '/controllers/'.$controllerStub);
        return $this->processStub($stub);
    }  

    /**
     * Compose widget file stub.
     *
     * @return string
     */
    protected function getRequestStub()
    {
        $stub = $this->files->get($this->stubsPath . '/requests/request.stub');
        return $this->processStub($stub);
    }   

    /**
     * Compose widget file stub.
     *
     * @return string
     */
    protected function getViewStub($fileName, $frontend = false)
    {
        $stubName = $fileName;
        if ($fileName === 'index') {
            $stubName = $this->request->get('softdeletes') ? 'index_with_softdeletes' : 'index';
            if ($frontend) {
                $stubName = 'frontend_index';
            }
        }
        $stub = $this->files->get($this->stubsPath . '/views/'.$stubName.'.stub');
        return $this->processStub($stub);
    }

    /**
     * Compose the Routes file stub.
     *
     * @return string
     */
    protected function getRoutesStub ($type = 'web', $frontend = false)
    {
        $prefix = $type === 'web' ? 'web' : 'api';
        $routesStub = $this->request->get('softdeletes') ? $prefix.'_with_softdeletes.stub' : $prefix.'.stub';
        if ($type === 'web' && $frontend === true) {
            $routesStub = 'web_frontend.stub';
        }
        $stub = $this->files->get($this->stubsPath . '/routes/'.$routesStub);
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

        $requestStubFilePath = $this->stubsPath.'/requests/request.stub';
        $controllerStubFilePath = $softdeletes ? $this->stubsPath.'/controllers/controller_with_softdeletes.stub' : $this->stubsPath.'/controllers/controller.stub';


        $apiControllerStubFilePath = $softdeletes ? $this->stubsPath.'/controllers/api_controller_with_softdeletes.stub' : $this->stubsPath.'/controllers/api_controller.stub';


        $formViewStubFilePath = $this->stubsPath.'/views/form.stub';
        $migrationStubFilePath = $this->stubsPath.'/database/migrations/migration.stub';
        $indexViewStubFilePath = $softdeletes ? $this->stubsPath.'/views/index_with_softdeletes.stub' : $this->stubsPath.'/views/index.stub';

        $requestStub = $this->files->get($requestStubFilePath);
        $controllerStub = $this->files->get($controllerStubFilePath);
        $apiControllerStub = $this->files->get($apiControllerStubFilePath);
        $formViewStub = $this->files->get($formViewStubFilePath);
        $migrationStub = $this->files->get($migrationStubFilePath);
        $indexViewStub = $this->files->get($indexViewStubFilePath);

        $textInputStub = $this->files->get($this->stubsPath.'/views/text-input.stub');
        $dateInputStub = $this->files->get($this->stubsPath.'/views/date-input.stub');
        $radioInputStub = $this->files->get($this->stubsPath.'/views/radio-input.stub');
        $stringInputStub = $this->files->get($this->stubsPath.'/views/string-input.stub');
        $fileInputStub = $this->files->get($this->stubsPath.'/views/file-input.stub');
        $imageInputStub = $this->files->get($this->stubsPath.'/views/image-input.stub');
        $indexSearchLineStub = $this->files->get($this->stubsPath.'/views/index-search.stub');

        // Frontend
        $frontendIndexStubFilePath = $this->stubsPath.'/views/frontend_index.stub';
        $frontendIndexStub = $this->files->get($frontendIndexStubFilePath);
        $frontendControllerStubFilePath = $this->stubsPath.'/controllers/frontend_controller.stub';
        $frontendControllerStub = $this->files->get($frontendControllerStubFilePath);


        // Write migration lines
        $migrationStatements = $this->request->get('migration');

        // check if migration contains `id` field. If it was accidentally deleted (user error), lets add it back
        $foundIdField = false;

        foreach ($migrationStatements as $migrationLine) {
            if ($migrationLine['field_name'] === 'id') {
                $foundIdField = true;
            }
        }

        if ($foundIdField === false) {
            $newFields = [];
            $newFields[] = [
                'data_type' => 'increments',
                'field_name' => 'id',
                'nullable' => false,
                'default' => null,
                'index' => null,
                'unique' => null,
                'show_index' => false,
                'can_search' => false,
                'is_file' => false,
                'file_type' => null,
                'in_form' => false
            ];

            $migrationStatements = array_merge($newFields, $migrationStatements);
        }

        $migrationLines = '';
        $controllerLines = '';
        $viewFormLines = '';
        $indexTableHeaderLines = '';
        $indexTableRows = '';
        $frontendIndexRows = '';
        $indexTableSearchLines = '';
        $controllerSearchLines = '';
        $requestLines = '';

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

            $migrationLines = $migrationLines.$statement.';'."\n"."\t\t\t";

            /**
             * Controller Optimization
             * */
            $tabs = strlen($controllerLines) < 1 ? '' : "\t\t";

            if ($isFile) {
                $fileUploadStatement = 'if ($request->file(\''.$fieldName.'\')) {'."\n";
                $fileUploadStatement = $fileUploadStatement."\t\t\t".'${{MODEL_ENTITY}}->'.$fieldName.' = $this->upload($request->file(\''.$fieldName.'\'), \''.$this->modelEntities.'\')->getFileName();'."\n";
                $fileUploadStatement = $fileUploadStatement."\t\t".'} else {'."\n";
                $fileUploadStatement = $fileUploadStatement."\t\t\t".'${{MODEL_ENTITY}}->'.$fieldName.' = ${{MODEL_ENTITY}}->'.$fieldName.';'."\n";
                $fileUploadStatement = $fileUploadStatement."\t\t".'}'."\n";
                $controllerLines = $controllerLines.$tabs.$fileUploadStatement;
            } else {
                $controllerLines = $controllerLines.$tabs.'${{MODEL_ENTITY}}->'.$fieldName.' = $request->get(\''.$fieldName.'\');'."\n";
            }
            

            /**
             * Request Rules optimization
             * */

            if (!in_array($fieldName, ['created_at', 'updated_at', 'deleted_at', 'id']) && $inForm) {
                $tab = strlen($requestLines) > 0 ? "\t\t" : '';
                $requestTab = strlen($requestLines) > 0 ? "\t\t\t" : '';
                // Write rules stub
                $required = $nullable ? "'nullable'" : "'required'";
                $fileRequest = ($isFile && $fileType === 'image') ? ", 'image'" : "";
                $dateFormat = ", 'date_format:Y-m-d H:i:s' ";
                $dateTime = in_array($dataType, $timeDataTypes) ? $dateFormat : "";
                $bool = $dataType === "boolean" ? ", 'boolean'" : "";
                $integer = Str::contains($dataType, 'int') ? ", 'integer'" : "";
                $float = Str::contains($dataType, 'double') ? ", 'numeric'" : "";
                $in = $dataType === 'enum' ? ", Rule::in(".$dataTypeParameter.")" : "";
                $requestLines = $requestLines.$requestTab."'$fieldName' => [".$required.$dateTime.$fileRequest.$bool.$in."],"."\n";

                /**
                 * View Form optimization
                 * */

                if (in_array($dataType, $timeDataTypes)) {
                    $viewFormLines = $viewFormLines.$dateInputStub."\n";
                } elseif ($dataType === 'boolean') {
                    $viewFormLines = $viewFormLines.$radioInputStub."\n";
                }elseif (in_array($dataType, $longTextDataTypes)) {
                    $viewFormLines = $viewFormLines.$textInputStub."\n";
                } elseif ($isFile) {
                    if ($fileType === 'image') {
                        $viewFormLines = $viewFormLines.$imageInputStub."\n";
                    } else {
                        $viewFormLines = $viewFormLines.$fileInputStub."\n";
                    }
                } else {
                    $viewFormLines = $viewFormLines.$stringInputStub."\n";
                }
                
            }

            if ($showIndex) {
                // Write index stub
                $indexTableHeaderLines = $indexTableHeaderLines.'<td>'.Str::title($fieldName).'</td>'."\n";
                $indexTableRows = $indexTableRows.'<td> {{ $'.$this->modelEntity.'Item->'.$fieldName.' }}</td>'."\n";
                $frontendIndexRows = $frontendIndexRows.'<br> {{ $'.$this->modelEntity.'Item->'.$fieldName.' }}'."\n";
            }

            if ($canSearch) {
                $indexTableSearchLines = $indexTableSearchLines.$indexSearchLineStub;
                $indexTableSearchLines = str_replace('{{FIELD_NAME_LABEL}}', Str::title($fieldName), $indexTableSearchLines);
                $indexTableSearchLines = str_replace('{{FIELD_NAME}}', $fieldName, $indexTableSearchLines);

                $controllerSearchLines =  $controllerSearchLines.
                                            "\n".
                                            "\t\t".
                                            'if ($request->has(\''.$line['field_name'].'\')) {'.
                                            "\n".
                                            "\t\t\t".
                                            '${{MODEL_ENTITIES}} = ${{MODEL_ENTITIES}}->where(\''.
                                            $line['field_name'].'\', \'LIKE\', \'%\'.$request->get(\''.
                                            $line['field_name'].'\').\'%\');'."\n"."\t\t".'}';
            }

            // Process view form lines
            $viewFormLines = str_replace('{{FIELD_NAME_LABEL}}', Str::title($fieldName), $viewFormLines);
            $viewFormLines = str_replace('{{FIELD_NAME}}', $fieldName, $viewFormLines);
            $viewFormLines = str_replace('{{FIELD_ID}}', Str::slug($fieldName), $viewFormLines);

            // Process view form responsiveness
            $viewFormLines = str_replace('COL_XS', 'col-xs-'.$col_xs, $viewFormLines);
            $viewFormLines = str_replace('COL_MD', 'col-md-'.$col_md, $viewFormLines);
            $viewFormLines = str_replace('COL_LG', 'col-lg-'.$col_lg, $viewFormLines);
        }

        $migrationLines = $migrationLines.'$table->timestamps();'."\n"."\t\t\t";

        if ($this->request->get('softdeletes')) {
            $migrationLines = $migrationLines.'$table->softdeletes();'."\n"."\t\t\t";
        }

        // Optimize and rewrite Stubs
        // Migration
        $migrationStub = str_replace('//MIGRATION_LINES', $migrationLines, $migrationStub);
        $this->files->put($migrationStubFilePath, $migrationStub);
        // Controller
        $controllerStub = str_replace('//POST_STATEMENTS', $controllerLines, $controllerStub);
        $controllerStub = str_replace('//SEARCH_STATEMENTS', $controllerSearchLines, $controllerStub);
        $this->files->put($controllerStubFilePath, $controllerStub);

        if ($this->request->get('api_code')) {
            $apiControllerStub = str_replace('//POST_STATEMENTS', $controllerLines, $apiControllerStub);
            $apiControllerStub = str_replace('//SEARCH_STATEMENTS', $controllerSearchLines, $apiControllerStub);
            $this->files->put($apiControllerStubFilePath, $apiControllerStub);
        }

        // Form View
        $formViewStub = str_replace('//FORM_LINES', $viewFormLines, $formViewStub);
        $this->files->put($formViewStubFilePath, $formViewStub);

        // Backend Index View
        $indexViewStub = str_replace('//TABLE_HEADER_LINES', $indexTableHeaderLines, $indexViewStub);
        $indexViewStub = str_replace('//TABLE_ROW_LINES', $indexTableRows, $indexViewStub);
        $indexViewStub = str_replace('//SEARCH_VIEW_LINES', $indexTableSearchLines, $indexViewStub);
        $this->files->put($indexViewStubFilePath, $indexViewStub);

        $frontendIndexStub = str_replace('//FRONTEND_ROWS', $frontendIndexRows, $frontendIndexStub);
        $this->files->put($frontendIndexStubFilePath, $frontendIndexStub);


        // Request Rules
        $requestStub = str_replace('//RULE_LINES', $requestLines, $requestStub);
        $this->files->put($requestStubFilePath, $requestStub);
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
