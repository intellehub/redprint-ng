<?php

namespace Shahnewaz\Redprint\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Shahnewaz\Redprint\Services\SettingsService;

class SettingsController extends Controller
{

	protected $settings;

	public function __construct()
	{
		$this->settings = new SettingsService;	
	}

	// Get settings
	public function getSettings(Request $request)
	{
		return view('redprint::redprint.settings.index');
	}

	public function getEnvContent(Request $request)
	{
		return response()->json($this->settings->getEnvContent());
	}

	public function setEnvContent(Request $request)
	{
		$this->settings->setEnvContent($request);
		return response()->json(['saved' => true]);
	}


	/**
	 * @param Request $request
	 * */
	public function getPermissibleConfig(Request $request)
	{
		return response()->json($this->settings->getPermissibleConfig());
	}

	/**
	 * @param Request $request
	 * */
	public function setPermissibleConfig(Request $request)
	{
		$this->settings->setPermissibleConfig($request);
		return response()->json(['saved' => true]);
	}

	/**
	 * @param Request $request
	 * */
	public function getRedprintConfig(Request $request)
	{
		return response()->json($this->settings->getRedprintConfig());
	}

	/**
	 * @param Request $request
	 * */
	public function setRedprintConfig(Request $request)
	{
		$this->settings->setRedprintConfig($request);
		return response()->json(['saved' => true]);
	}

	/**
	 * @param Request $request
	 * */
	public function getThemeConfig(Request $request)
	{
		return response()->json($this->settings->getThemeConfig());
	}

	/**
	 * @param Request $request
	 * */
	public function setThemeConfig(Request $request)
	{
		$this->settings->setThemeConfig($request);
		return response()->json(['saved' => true]);
	}

	/**
	 * @param Request $request
	 * */
	public function getRedprintMenu(Request $request)
	{
		//TODO
	}

	/**
	 * @param Request $request
	 * */
	public function setRedprintMenu(Request $request)
	{
		//TODO
	}
}
