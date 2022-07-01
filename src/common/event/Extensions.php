<?php

namespace tpext\manager\common\event;

use tpext\common\ExtLoader;
use tpext\manager\common\logic\ExtensionLogic;

class Extensions
{
    public function handle($data)
    {
        $logic = new ExtensionLogic;

        $findExtensions = $logic->getExtendExtensions();

        ExtLoader::addClassMap($findExtensions);

        return true;
    }
}
