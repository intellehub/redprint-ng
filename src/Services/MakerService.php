<?php
namespace Shahnewaz\Redprint\Services;

use Artisan;
use Redprint;
use Validator;
use Monolog\Logger;
use Illuminate\Http\Request;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\Filesystem;
use Shahnewaz\Redprint\Exceptions\BuildProcessException;

class MakerService {
    /**
     * Request
     * */
    protected $request;

    /**
     * @var store process errors
     * */
    protected $errorsArray;
    
    /**
     * @var process informations
     * */
    protected $infoArray;

    /**
     * @var process informations
     * */
    protected $files;

    /**
     * Service construct
     * @param Request $request
     * */
    public function __construct(Request $request)
    {
        $logger = new Logger('BuilderServiceLog');
        $logger->pushHandler(new StreamHandler(storage_path('BuilderService.log'), Logger::INFO));
        $this->errorsArray = [];
        $this->infoArray = [];
        $this->request = $request;
        $this->files = new Filesystem;
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
     * run a Make command from request
     * @param Request $request
     * @return mixed
     * */
    public function makeFromRequest()
    {
        $this->cleanup();
        return $this->call();
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
    }

    /**
     * Root path for package files
     * */
    private function packagePath($path)
    {
        return __DIR__."/../../$path";
    }

    /**
     * Call artisan
     * @return
     * */
    public function call()
    {
        // Directory where command will be run from
        $directory = base_path();
        // Command to run
        $command = $this->request->get('command');
        $type = $this->request->get('type');
        $param = $this->request->get('param');
        $option = $this->request->get('option');
        $arguments = [];

        if ($command === 'view') {
            // Laravel doesn't have it.
            // Explode by dot if there's a dot
            $hasDot = strpos($param, '.') !== FALSE;
            
            if($hasDot) {
                $param = str_replace('.', '/', $param);
            }

            $destinationViewPath = resource_path('views/'.$param.'.blade.php');

            $permission = 0644;
            
            if (!$this->files->isDirectory(dirname($destinationViewPath))) {
                $this->files->makeDirectory(dirname($destinationViewPath), $permission, true, true);
            }

            $viewStubPath = $this->packagePath('stubs/views/vanilla.stub');
            $this->files->copy($viewStubPath, $destinationViewPath);
            return 'View file '.$param.'.blade.php created successfully!';
        }

        switch ($type) {
            case 'make':
                $generatedCommand = 'make:'.$command;
                break;
            case 'artisan':
                $generatedCommand = $command;
                break;
            case 'composer':
                $generatedCommand = $command;
                break;
            default:
                $generatedCommand = $command;
                break;
        }
        
        
        if ($param) {
            $arguments['name'] = $param;    
        }

        if ($option) {
            $arguments['--'.$option] = true;   
        }
        
        if ($type === 'composer') {
            $generatedCommand = 'composer '.$generatedCommand;
        }

        if ($type === 'sniffer') {
            $generatedCommand = $generatedCommand.' '.base_path('app');
            $directory = base_path('vendor/bin');
        }


        if ($type === 'system' || $type === 'composer' || $type === 'sniffer') {

            if(redprint_demo()) {
                throw new \Exception('DISABLED FOR DEMO.', 422);
            }

            $output = $this->systemCommand($generatedCommand, $directory);
            if (strlen($output['stderr']) > 2) {
                throw new \Exception($output['stderr'], 422);
            }
            return $output['stdout'];
        }

        if ($generatedCommand === 'migrate:rollback' && redprint_demo()) {
            throw new \Exception('DISABLED FOR DEMO.', 422);
        }

        Artisan::call($generatedCommand, $arguments);
        return Artisan::output();
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
