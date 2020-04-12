<?php

  // Check if App is running in Redprint mode
  function redprint()
  {
    return (env('APP_ENV', 'local') === 'REDPRINT' || env('APP_ENV', 'local') === 'REDPRINT_DEMO');
  }

  function redprint_demo()
  {
    return (env('APP_ENV', 'local') === 'REDPRINT_DEMO');
  }

	function redprintMenu()
  {
    if (redprint()) {
      return redprintRoutesArray();
    } 
    return [];
	}

	function redprintRoutesArray()
  {
    $menu = include(__DIR__.'/../../config/menu.php');
    $updaterMenu = function_exists('intelleUpdaterMenu') ? intelleUpdaterMenu() : [];
		return array_merge($menu, $updaterMenu);
	}

  function getRedprintVersion()
  {
    $version = json_decode(file_get_contents(__DIR__.'/../../version.json'));
    return $version->current;
  }

  function count_recursive($array)
  {
    $i = 0;
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $data) {
          $i++;
        }
      }
    }
    return $i;
  }

  function redprint_resource_path($path = null)
  {
    return base_path('vendor/shah-newaz/redprint/resources/views/redprint/'.$path);
  }

  // Convert text representations for boolean to actual boolean values
  function toBool($string)
  {
      $string = strtolower($string);
      $value = 0;
      if ($string === "true") {
          $value = 1;
      }
      if ($string === "false") {
          $value = 0;
      }
      return boolval($value);
  }