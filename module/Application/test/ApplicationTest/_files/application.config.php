<?php

use Zend\Stdlib\ArrayUtils;

$configOverrides = [
    'module_listener_options' => [
        'config_glob_paths' => [
            'module/Application/test/ApplicationTest/_files/local.php',
        ]
    ]
];

$merged = ArrayUtils::merge(
    include __DIR__ . '/../../../../../config/application.config.php',
    $configOverrides
);

$merged['module_listener_options']['config_glob_paths'] = [
    'module/Application/test/ApplicationTest/_files/local.php',
];

return $merged;
