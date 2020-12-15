<?php

namespace tpext\manager\common;

use think\Service as BaseService;
use tpext\common\ExtLoader;

/**
 * for tp6
 */
class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->listen('tpext_find_extensions', function () {

            $config = Module::getInstance()->config();

            if (isset($config['find_extensions']) && !empty($config['find_extensions'])) {

                $findExtensions = str_replace(['|', "\n"], ',', $config['find_extensions']);
                $findExtensions = str_replace([' ', "\r"], '', $findExtensions);
                $findExtensions = str_replace(["/", "\\\\"], '\\', $findExtensions);
                $findExtensions = array_filter(explode(',', $findExtensions), 'trim');

                ExtLoader::addClassMap($findExtensions);
            }
        });
    }
}
