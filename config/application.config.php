<?php

return [
    'modules' => [
        'Laminas\\Db',
        'Laminas\\Form',
        'Laminas\\Filter',
        'Laminas\\I18n',
        'Laminas\\InputFilter',
        'Laminas\\Hydrator',
        'Laminas\\Log',
        'Laminas\\Mail',
        'Laminas\\Mvc\\I18n',
        'Laminas\\Mvc\\Console',
        'Laminas\\Router',
        'Laminas\\Session',
        'Laminas\\Validator',
        'Autowp\\User',
        'Autowp\\Comments',
        'Autowp\\Cron',
        'Autowp\\ExternalLoginService',
        'Autowp\\Forums',
        'Autowp\\Image',
        'Autowp\\Message',
        'Autowp\\TextStorage',
        'Autowp\\Traffic',
        'Autowp\\Votings',
        'Autowp\\ZFComponents',
        'Application',
        //'ZF\ContentNegotiation',
        //'ZF\OAuth2',
    ],

    // These are various options for the listeners attached to the ModuleManager
    'module_listener_options' => [
        // This should be an array of paths in which modules reside.
        // If a string key is provided, the listener will consider that a module
        // namespace, the value of that key the specific path to that module's
        // Module class.
        'module_paths' => [
            './module',
            './vendor',
        ],

        // Whether or not to enable a configuration cache.
        // If enabled, the merged configuration will be cached and used in
        // subsequent requests.
        'config_cache_enabled' => true,

        // The key used to create the configuration cache file name.
        'config_cache_key' => 'config_cache',

        // Whether or not to enable a module class map cache.
        // If enabled, creates a module class map cache which will be used
        // by in future requests, to reduce the autoloading process.
        'module_map_cache_enabled' => true,

        // The key used to create the class map cache file name.
        'module_map_cache_key' => 'module_map_cache',

        // The path in which to cache merged configuration.
        'cache_dir' => __DIR__ . '/../cache/modulecache',

        // Whether or not to enable modules dependency checking.
        // Enabled by default, prevents usage of modules that depend on other modules
        // that weren't loaded.
        // 'check_dependencies' => true,
    ]
];
