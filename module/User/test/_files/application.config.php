<?php

$merged = include __DIR__ . '/../../../../config/application.config.php';

$merged['module_listener_options'] = array_replace($merged['module_listener_options'], [
    'config_glob_paths' => [
        'module/User/test/_files/local.php',
    ],
    'config_cache_enabled' => true,
    'module_map_cache_enabled' => true,
]);

return $merged;
