<?php

namespace tpext\manager\admin\model;

use think\Model;

class WebConfig extends Model
{
    protected $autoWriteTimestamp = 'dateTime';

    public static function clearCache($configKey)
    {
        cache('web_config_' . $configKey, null);
    }

    public static function config($configKey, $reget = false)
    {
        $cache = cache('web_config_' . $configKey);

        if ($cache && !$reget) {
            return $cache;
        }

        $theConfig = static::where(['key' => $configKey])->find();
        if (!$theConfig) {
            return [];
        }

        $rootPath = app()->getRootPath();
        $filePath = $rootPath . $theConfig['file'];

        if (!is_file($filePath)) {
            $this->error('原始配置文件不存在：' . $theConfig['file']);
        }

        $default = include $filePath;

        $config = json_decode($theConfig['config'], 1);

        if (empty($config)) {
            return $default;
        }

        $values = [];
        foreach ($default as $key => $val) {

            if ($key == '__config__') {
                continue;
            }

            $values[$key] = $config[$key];

            if (is_array($val)) {
                $values[$key] = json_decode($config[$key], 1);
            }
        }
        if (!empty($values)) {
            cache('web_config_' . $configKey, $values);
        }

        return $values;
    }
}
