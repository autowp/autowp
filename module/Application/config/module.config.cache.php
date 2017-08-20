<?php

namespace Application;

$host = getenv('AUTOWP_MEMCACHED_HOST');

return [
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
    ]
];
