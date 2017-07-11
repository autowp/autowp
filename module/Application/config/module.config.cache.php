<?php

namespace Application;

use Zend_Cache_Manager;

$host = getenv('AUTOWP_MEMCACHED_HOST');

return [
    'cachemanager' => [
        'fast' => [
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 1800,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'Memcached',
                'options' => [
                    'servers' => [
                        'host' => $host,
                        'port' => 11211
                    ]
                ]
            ]
        ]
    ],
    'caches' => [
        'fastCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'options'  => [
                    'ttl' => 180,
                    'servers'   => [
                        'main' => [
                            'host' => $host,
                            'port' => 11211
                        ]
                    ],
                    'namespace'  => 'FAST',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
        ],
        'longCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'options'  => [
                    'ttl' => 600,
                    'servers'   => [
                        'main' => [
                            'host' => $host,
                            'port' => 11211
                        ]
                    ],
                    'namespace'  => 'LONG',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
        ],
        'localeCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'options'  => [
                    'servers'   => [
                        'main' => [
                            'host' => $host,
                            'port' => 11211
                        ]
                    ],
                    'namespace'  => 'LOCALE',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
        ],
        'sessionCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'options'  => [
                    'ttl' => 864000,
                    'servers'   => [
                        'main' => [
                            'host' => $host,
                            'port' => 11211
                        ]
                    ],
                    'namespace'  => 'LONG',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            Zend_Cache_Manager::class => Service\ZF1CacheManagerFactory::class,
        ]
    ]
];
