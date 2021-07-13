<?php

namespace tpext\manager\common;

use think\Service as BaseService;
use tpext\common\ExtLoader;
use tpext\manager\common\logic\ExtensionLogic;

/**
 * for tp6
 */
class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->listen('tpext_find_extensions', function () {

            $logic = new ExtensionLogic;

            $findExtensions = $logic->getExtendExtensions();

            ExtLoader::addClassMap($findExtensions);
        });
    }
}
