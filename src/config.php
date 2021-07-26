<?php

return [
    'model_namespace' => 'common',
    //配置描述
    '__config__' => [
        'model_namespace' => ['type' => 'radio', 'label' => '生成model命名空间', 'options' => ['common' => 'app\\common\\model', 'admin' => 'app\\admin\\model']],
    ],
];
