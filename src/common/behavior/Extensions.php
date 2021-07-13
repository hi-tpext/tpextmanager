<?php

namespace tpext\manager\common\behavior;

use tpext\common\ExtLoader;
use tpext\manager\common\logic\ExtensionLogic;

class Extensions
{
    public function run()
    {
        $logic = new ExtensionLogic;

        $findExtensions = $logic->getExtendExtensions();

        ExtLoader::addClassMap($findExtensions);
    }
}
