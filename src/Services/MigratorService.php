<?php
namespace Shahnewaz\RedprintNg\Services;

use DB;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Shahnewaz\RedprintNg\Services\Migrator as RedprintMigrator;

class MigratorService
{
    public $request;
    public $migrator;
    public $files;
    protected $cleanupMigrationFiles;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->migrator = new Migrator;
        $this->files = new Filesystem;
    }

    public function rollback()
    {
        // Rollback migrations
        $modelName = $this->request->get('Model');
        $files = $this->request->get('files');
        $migrationsToRollback = $this->getMigrations();
        $this->cleanupMigrationFiles = $migrationsToRollback;
        // Some migrations may fail
        try {
            $rolledBack = $this->migrator->rollbackSelectedMigrations($migrationsToRollback);
        } catch (\Exception $e) {
            // Silence
        }

        // Delete files
        $filesCollection = $this->request->get('files');
        foreach ($filesCollection as $file) {
            if ($file['conflict'] === "true") {
                $this->files->delete(base_path($file['path']));
            } else {
                $this->repairFileByPath($file['path']);
            }
        }
        $this->reverseMenu();
        $this->cleanup();
        return true;
    }

    public function getMigrations()
    {
        $tableName = Str::snake(Str::plural($this->request->get('model')));
        $suffix = $tableName.'_table';
        return DB::table('migrations')
            ->where('migration', $this->request->get('migration'))
            ->orWhere('migration', 'LIKE', '%'.$suffix.'%')
            ->get()->all();
    }

    public function repairFileByPath($path)
    {
        $backupPath = storage_path($this->request->get('id').'/'.$path.'.bak');
        if (!$this->files->exists($backupPath)) {
            return false;
        }
        $backup = $this->files->get($backupPath);
        $currentFileContents = $this->files->get(base_path($path));
        $currentFileContents = str_replace($backup, '', $currentFileContents);
        
        $this->files->put(base_path($path), $currentFileContents);        
    }


    public function reverseMenu()
    {
        $menuPath = base_path('config/backend_menu.php');
        $route = Str::camel($this->request->get('model')).'.index';
        $currentMenu = array_values(config('backend_menu'));
        
        $menu = collect($currentMenu);
        $menuFiltered = $menu->where('route', '!=', $route);
        $menuCollection = $menuFiltered->all();

        $builtMenu = var_export($menuCollection, true);
        $content = $this->files->get(storage_path($this->request->get('id')) . '/stubs/config/backend_menu.php');
        $content = str_replace('//MENU', $builtMenu, $content);
        $content = str_replace('array', '', $content);
        $content = str_replace('(', '[', $content);
        $content = str_replace(')', ']', $content);

        $this->files->put($menuPath, $content);
    }


    /**
     * Do a general cleanup for autoloads and compiled class names
     * @return
     * */
    public function cleanup()
    {
        // Dump Composer autoload
        try {
            $process = system('composer dump-autoload');
        } catch (\Exception $e) {
            // Silence!
        }
        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        $commandDir = base_path('vendor/bin');
        $generatedCommandForApp = 'phpcbf '.base_path('app');
        $generatedCommandForRoutes = 'phpcbf '.base_path('routes');

        $outputAppCommand = $this->systemCommand($generatedCommandForApp, $commandDir);
        $outputRoutesCommand = $this->systemCommand($generatedCommandForRoutes, $commandDir);

        $this->files->deleteDirectory(storage_path($this->request->get('id').'/stubs'));
        $this->files->deleteDirectory(storage_path($this->request->get('id')));
        // Remove all migratiion and files regardless
        foreach ($this->cleanupMigrationFiles as $migration) {
            try {
                $this->files->delete(base_path('database/migrations/'.$migration->migration.'.php'));
                DB::table('migrations')->where('migration', $migration->migration)->delete();
            } catch (\Exception $e) {
                
            }
        }
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

