<?php

namespace Application;

return [
    'caches' => [
        'fastCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 180,
                'options'  => [
                    'ttl' => 180,
                    'servers'   => [
                        ['localhost', 11211]
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
            /*'plugins' => [
             'exception_handler' => [
                 'throw_exceptions' => false
             ],
            ],*/
        ],
        'longCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 600,
                'options'  => [
                    'ttl' => 600,
                    'servers'   => [
                        ['localhost', 11211]
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
            /*'plugins' => [
             'exception_handler' => [
                 'throw_exceptions' => false
             ],
            ],*/
        ],
        'localeCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 600,
                'options'  => [
                    'servers'   => [
                        ['localhost', 11211]
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
            /*'plugins' => [
             'exception_handler' => [
                 'throw_exceptions' => false
             ],
            ],*/
        ],
    ],
];
