<?php
namespace Shahnewaz\RedprintNg\Services;

use Config;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;
use October\Rain\Config\DataWriter\Rewrite as ConfigRewrite;
use Shahnewaz\RedprintNg\Exceptions\BuildProcessException;

class SettingsService {

    /**
     * @var process informations
     * */
    protected $files;

    /**
     * Service construct
     * @param Request $request
     * */
    public function __construct()
    {
        $this->files = new Filesystem;
    }

    public function getEnvContent()
    {
        $data = collect(DotenvEditor::getKeys());
        $except = [];

        if (redprint_demo()) {
            $except = ['APP_KEY', 'JWT_SECRET', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'REDIS_PASSWORD', ];
        }

        $mapped = array_values($data->map(function ($item, $key) {
            $protected = ['APP_KEY', 'JWT_SECRET'];
            $passwords = ['DB_PASSWORD', 'REDIS_PASSWORD'];
            $item['key'] = $key;
            $item['protected'] = in_array($key, $protected) ? true : false;
            return $item;
        })->whereNotIn('key', $except)->all());
        return $mapped;
    }

    public function setEnvContent(Request $request)
    {
        $data = $request->get('data');
        DotenvEditor::setKeys($data)->save();
        return true;
    }

    public function getConfig($config, $except = [], $protected = [])
    {
        $config = collect(Config::get($config));
        $mapped = $config->map(function($item, $key) use ($protected) {
            $configItem = [];
            $configItem['key'] = $key;
            $configItem['value'] = $item;
            $configData['protected'] = in_array($key, $protected) ? true : false;
            return $configItem;
        })->whereNotIn('key', $except)->all();
        return $mapped;
    }

    public function setConfig(Request $request, $config)
    {
        $data = array_values($request->get('data'));
        $configData = [];
        foreach ($data as $item) {
            if (in_array(strtolower($item['value']), ['true', 'false'])) {
                $item['value'] = toBool($item['value']);
            }
            $configData[$item['key']] = $item['value'];
        }

        $writeConfig = new ConfigRewrite;
        $writeConfig->toFile(config_path($config.'.php'), $configData);
        return true;
    }

    // Redprint
    public function getRedprintConfig()
    {
        return $this->getConfig('redprint');
    }

    public function setRedprintConfig(Request $request)
    {
        return $this->setConfig($request, 'redprint');
    }

    // Permissible
    public function getPermissibleConfig()
    {
        return $this->getConfig('permissible');
    }

    public function setPermissibleConfig(Request $request)
    {
        return $this->setConfig($request, 'permissible');
    }

    // Redprint Unity
    public function getThemeConfig()
    {
        return $this->getConfig('redprintUnity', ['menu', 'filters']);
    }

    public function setThemeConfig(Request $request)
    {
        return $this->setConfig($request, 'redprintUnity');
    }

}
