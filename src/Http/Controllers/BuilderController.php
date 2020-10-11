<?php

namespace Shahnewaz\RedprintNg\Http\Controllers;

use DB;
use Redprint;
use File;
use Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Shahnewaz\RedprintNg\Services\BuilderService;
use Shahnewaz\RedprintNg\Services\BuilEditService;
use Shahnewaz\RedprintNg\Services\MigratorService;
use Shahnewaz\RedprintNg\Http\Requests\BuilderRequest;

class BuilderController extends Controller
{
    public function index () {
        $dataTypes = Redprint::dataTypes();
        return view('redprint::redprint.builder.index')->withDataTypes($dataTypes);
    }

    public function edit ($crudId) {
        $dataTypes = Redprint::dataTypes();
        
        $filePath = storage_path('redprint/'.$crudId.'/crud.json');
        if(!file_exists($filePath)) {
            abort(404);
        }
        $crudJson = File::get($filePath);

        try {
            $crudJson = json_decode($crudJson, true);
            $migrations = $crudJson['original_request'][0]['migration'];
            $model = $crudJson['original_request'][0]['model'];
            $softdeletes = $crudJson['original_request'][0]['softdeletes'];
            $apiCode = $crudJson['original_request'][0]['api_code'];
        } catch (\Exception $e) {
            abort(404);
        }

        // Check for revisions and add them to migrations
        $revisions = [];

        if($crudJson['revisions']) {
            foreach ($crudJson['revisions'] as $rev) {
                if(is_array($rev)) {
                    foreach ($rev['migration'] as $migr) {
                        $migrations[] = $migr;
                    }
                    
                }
            }
        }

        return view('redprint::redprint.builder.edit')
                ->withDataTypes($dataTypes)
                ->with('crud_id', $crudId)
                ->with('model', $model)
                ->with('migrations', $migrations)
                ->with('softdeletes', $softdeletes)
                ->with('api_code', $apiCode);
    }

    public function build (BuilderRequest $request) {
        $builder = new BuilderService($request);
        $response = $builder->buildFromRequest($request);
        return response()->json(['route' => '/backend/'.$response]);
    }


    public function postEditCrud (BuilderRequest $request) {
        $builder = new BuilEditService($request);
        $response = $builder->editFromRequest($request);
        return response()->json(['route' => '/backend/'.$response]);
    }

    public function buildVerbose (Request $request) {
        $builder = new BuilderService($request);
        $response = $builder->buildFromRequest($request);
    }

    public function rollback(Request $request)
    {
        $migrator = new MigratorService($request);
        $response = $migrator->rollback();
        return response()->json(['rollback' => true, 'response' => $response]);
    }
}
