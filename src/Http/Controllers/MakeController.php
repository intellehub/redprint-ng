<?php

namespace Shahnewaz\Redprint\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Shahnewaz\Redprint\Services\MakerService;

class MakeController extends Controller
{
  public function index () {
    return view('redprint::redprint.make-tools.index');
  }

  public function post (Request $request) {
    $maker = new MakerService($request);
    $response = $maker->makeFromRequest($request);
    return response()->json(['message' => $response]);
  }
}
