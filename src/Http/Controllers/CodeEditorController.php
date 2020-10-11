<?php

namespace Shahnewaz\RedprintNg\Http\Controllers;

use File;
use Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\Filesystem;

class CodeEditorController extends Controller
{

    /**
     * Get file list for CodeEditor Sidebar
     * */
    public function getSidebarFileList()
    {
      $config = $this->getFiles('config', 'php', true, [], true);
      $models = $this->getFiles('app', 'php', true, ['Http', 'Console', 'Providers', 'Traits', 'Exceptions'], true);
      $controllers = $this->getFiles('app/Http/Controllers', 'php', true, [], true);
      $views = $this->getFiles('resources/views', 'html' ,true, [], true);
      $migrations = $this->getFiles('database/migrations', 'php', true, [], true);
      $routes = $this->getFiles('routes', 'php', true, [], true);

      $console = $this->getFiles('app/Console', 'php', true, [], true);
      $requests = $this->getFiles('app/Http/Requests', 'php', true, [], true);
      $providers = $this->getFiles('app/Providers', 'php', true, [], true);
      $exceptions = $this->getFiles('app/Exceptions', 'php', true, [], true);


      return response()->json(
        [
          'config' => $config,
          'models' => $models,
          'controllers' => $controllers,
          'views' => $views,
          'migrations' => $migrations,
          'routes' => $routes,
          'console' => $console,
          'requests' => $requests,
          'providers' => $providers,
          'exceptions' => $exceptions,
        ]
      );

    }

    /**
     * Get list of files for search term
     * @param Request $request
     * @return JSON
     * */
    public function getFileList(Request $request)
    {
      $requestTerm = $request->get('term');
      // Take the name only and get rid of extensions
      $termParts = explode('.', $requestTerm);
      $term = $termParts[0];

      $config = $this->getFiles('config', 'php', true);
      $models = $this->getFiles('app', 'php', true, ['Http', 'Console', 'Providers', 'Traits', 'Exceptions']);
      $controllers = $this->getFiles('app/Http/Controllers', 'php', true);
      $views = $this->getFiles('resources/views', 'html' ,true);
      $migrations = $this->getFiles('database/migrations', 'php', true);
      $routes = $this->getFiles('routes', 'php', true);

      $allFiles = array_merge($config, $models, $controllers, $views, $migrations, $routes);
      $filesCollection = collect($allFiles);
      $filtered = $filesCollection->filter(function ($item) use ($term) {
          if (!$term) {
            return true;
          }
          // replace stristr with your choice of matching function
          return false !== stristr($item['name'], $term);
      })->take(5)->all();
      return response()->json($filtered);
    }

    /**
     * Get a file content from file path
     * @param Request $request
     * @return JSON
     * */
    public function getFileContent(Request $request)
    {
        $filePath = $request->get('path');
        $filePath = str_replace('/', '\\', $filePath);
        $filePath = str_replace('\\', '/', $filePath);
        $fileContent = File::get(base_path($filePath));
        return response()->json(['content' => $fileContent]);
    }

    /**
     * Put file content to a file
     * @param Request $request
     * @return JSON
     * */
    public function putFileContent(Request $request)
    {
        if (redprint_demo()) {
          throw new \Exception('Cannot save in demo mode.');
        }

        $filePath = $request->get('path');
        $filePath = str_replace('/', '\\', $filePath);
        $filePath = str_replace('\\', '/', $filePath);
        $fileContent = File::put(base_path($filePath), $request->get('content')."\n");
        return response()->json(['content' => $fileContent]);
    }


    public function createNewFile(Request $request)
    {
        $param = $request->get('name');
        $path = $request->get('path');
        // Normalize slashes
        $hasBackwardSlash = strpos($param, '\\') !== FALSE;
        if($hasBackwardSlash) {
            $param = str_replace('\\', '/', $param);
        }
        $param = str_replace('//', '/', $param);

        $destinationPath = base_path($path.'/'.$param);
        $permission = 0644;

        $fileSystem = new Filesystem;
        
        if ($fileSystem->exists($destinationPath)) {
          throw new \Exception('File already exists');
        }

        if (!$fileSystem->isDirectory(dirname($destinationPath))) {
            $fileSystem->makeDirectory(dirname($destinationPath), $permission, true, true);
        }
        $createdFile = $fileSystem->put($destinationPath, '// New File'."\n");

        $responsePath = $path.'/'.$param;
        $responsePath = str_replace('\\', '/', $responsePath);
        $responsePath = str_replace('//', '/', $responsePath);
        return response()->json(['path' => $responsePath]);
    }

    /**
     * Delete a file
     * */
    public function deleteFile(Request $request)
    {
        if (redprint_demo()) {
          throw new \Exception('Cannot delete in demo mode.');
        }
        
        $filePath = $request->get('path');
        $filePath = str_replace('/', '\\', $filePath);
        $filePath = str_replace('\\', '/', $filePath);
        $del = File::delete(base_path($filePath));
        return response()->json(['deleted' => true]);
    }

    /**
     * Get a list of current files on a dir
     * @return  Array list of file names
     * */
    public function getFiles ($path, $lang, $recursive = false, $exclude = [], $byDirectory = false) {
        $basePath = $path;

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

              $filePath = $basePath.'/'.$file->getRelativePath().'/'.$file->getFilename();
              $filePath = str_replace('\\', '/', $filePath);
              $filePath = str_replace('//', '/', $filePath);

              $extension = '.'.$file->getExtension();

              if ($byDirectory) {
                $filesData[$file->getRelativePath()][] = [
                  'name' =>  $file->getBasename($extension),
                  'lang' => $lang,
                  'path' => $filePath,
                  'ext' => $file->getExtension()
                ];

              } else {
                $filesData[] = [
                  'name' =>  $file->getBasename($extension),
                  'lang' => $lang,
                  'path' => $filePath,
                  'ext' => $file->getExtension()
                ];
              }
            }
        }

        return $filesData;
    }


}
