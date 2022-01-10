<?php

declare(strict_types=1);

namespace Application;

return [
    'caches' => [
        'fastCache' => [
            'adapter' => 'memcached',
            'options' => [
                'ttl'        => 180,
                'servers'    => [
                    'main' => [
                        'host' => 'memcached',
                        'port' => 11211,
                    ],
                ],
                'namespace'  => 'FAST',
                'liboptions' => [
                    'COMPRESSION'     => false,
                    'binary_protocol' => true,
                    'no_block'        => true,
                    'connect_timeout' => 100,
                ],
            ],
        ],
        'longCache' => [
            'adapter' => 'memcached',
            'options' => [
                'ttl'        => 600,
                'servers'    => [
                    'main' => [
                        'host' => 'memcached',
                        'port' => 11211,
                    ],
                ],
                'namespace'  => 'LONG',
                'liboptions' => [
                    'COMPRESSION'     => false,
                    'binary_protocol' => true,
                    'no_block'        => true,
                    'connect_timeout' => 100,
                ],
            ],
        ],
    ],
];
