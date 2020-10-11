<?php

namespace Shahnewaz\RedprintNg\Http\Controllers;

use File;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{

    /**
     * Load Dashboard
     * */
    public function index () {
      return view('redprint::redprint.dashboard');
    }

    public function getGenerated(Request $request)
    {
      return view('redprint::redprint.generated');
    }


    public function getCruds(Request $request)
    {
      $crudFiles = File::allFiles(storage_path('redprint'));
      $cruds = [];

      foreach ($crudFiles as $file) {
        if ($file->getFilename() === 'crud.json') {
          $cruds[] = json_decode($file->getContents());
        }
      }
      return response()->json($cruds);
    }

    /**
     * Load API Tester
     * */
    public function apiTester(Request $request)
    {
        return view('redprint::redprint.builder.api');
    }

}
