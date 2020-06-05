<?php

namespace tpext\manager\common;

use tpext\common\Module as baseModule;

class Module extends baseModule
{
    protected $version = '1.0.1';

    protected $name = 'tpext.manager';

    protected $title = 'tpext扩展管理';

    protected $description = '提供对扩展的管理[安装/卸载/资源刷新/配置]';

    protected $__root__ = __DIR__ . '/../../';

    protected $modules = [
        'admin' => ['config', 'extension', 'attachment','table'],
    ];
}
