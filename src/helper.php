<?php

use tpext\common\ExtLoader;
use tpext\manager\common\behavior\Extensions;

$classMap = [
    'tpext\\manager\\common\\Module'
];

ExtLoader::addClassMap($classMap);

ExtLoader::watch('tpext_find_extensions', Extensions::class, true, '初始化tpext');