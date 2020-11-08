<?php

namespace Application;

return [
    'db'                       => [
        'dbname'   => 'autowp_test',
        'username' => 'autowp_test',
        'password' => 'test',
    ],
    'imageStorage'             => [
        'srcOverride' => [
            'host' => '127.0.0.1',
            'port' => '9000',
        ],
        's3'          => [
            'endpoint'    => [
                'http://minio:9000',
            ],
            'credentials' => [
                'key'    => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            ],
        ],
    ],
    'fileStorage'              => [
        'srcOverride' => [
            'host' => '127.0.0.1',
            'port' => '9000',
        ],
        's3'          => [
            'endpoint'    => [
                'http://minio:9000',
            ],
            'credentials' => [
                'key'    => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            ],
        ],
    ],
    'mosts_min_vehicles_count' => 1,
];
