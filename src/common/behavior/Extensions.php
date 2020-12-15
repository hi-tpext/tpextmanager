<?php

namespace tpext\manager\common\behavior;

use tpext\common\ExtLoader;
use tpext\manager\common\Module;

class Extensions
{
    public function run()
    {
        $config = Module::getInstance()->config();

        if (isset($config['find_extensions']) && !empty($config['find_extensions'])) {

            $findExtensions = str_replace(['|', "\n"], ',', $config['find_extensions']);
            $findExtensions = str_replace([' ', "\r"], '', $findExtensions);
            $findExtensions = str_replace(["/", "\\\\"], '\\', $findExtensions);
            $findExtensions = array_filter(explode(',', $findExtensions), 'trim');

            ExtLoader::addClassMap($findExtensions);
        }
    }
}
