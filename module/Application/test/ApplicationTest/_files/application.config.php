<?php

use Zend\Stdlib\ArrayUtils;

$configOverrides = [
    'module_listener_options' => [
        'config_glob_paths' => [
            'config/autoload/{{,*.}global,{,*.}local}.php',
            'module/Application/test/ApplicationTest/_files/local.php',
        ]
    ]
];

return ArrayUtils::merge(
    include __DIR__ . '/../../../../../config/application.config.php',
    $configOverrides
);