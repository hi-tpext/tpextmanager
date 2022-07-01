<?php

use tpext\common\ExtLoader;
use tpext\manager\common\event\Extensions;

$classMap = [
    'tpext\\manager\\common\\Module'
];

ExtLoader::addClassMap($classMap);

if (ExtLoader::isWebman()) {
    ExtLoader::watch('tpext_find_extensions',  Extensions::class, true, '/extend/目录扫描');
}
