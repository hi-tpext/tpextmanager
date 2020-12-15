<?php

use tpext\common\ExtLoader;

$classMap = [
    'tpext\\manager\\common\\Module'
];

ExtLoader::addClassMap($classMap);

ExtLoader::watch('tpext_find_extensions', tpext\manager\common\behavior\Extensions::class, true, '初始化tpext');