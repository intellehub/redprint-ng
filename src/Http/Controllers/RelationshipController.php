<?php

namespace Shahnewaz\RedprintNg\Http\Controllers;

use Redprint;
use Redprintship;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RelationshipController extends Controller
{
    public function index () {
        $relations = Redprintship::getRelations();
        $models = array_keys($relations);
        $relationshipTypes = Redprint::relationshipIdentifiers();
        return view('redprint::redprint.relationship.index')
        				->with('relations', $relations)
        				->with('models', $models)
        				->with('relationshipTypes', $relationshipTypes);
    }

    public function postNew (Request $request) {
    	$build = Redprintship::writeRelation($request);
        $type = $build['code'] === 201 ? 'success' : 'message';
    	return redirect()->back()->with($type, $build['message']);
    }
}
