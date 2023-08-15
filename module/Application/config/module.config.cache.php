<?php

declare(strict_types=1);

namespace Application;

use Laminas\Cache\Storage\Adapter\Redis;

return [
    'caches' => [
        'fastCache' => [
            'adapter' => Redis::class,
            'options' => [
                'ttl'       => 180,
                'server'    => [
                    'host'    => 'redis',
                    'port'    => 6379,
                    'timeout' => 10,
                ],
                'namespace' => 'FAST',
            ],
            'plugins' => [['name' => 'serializer']],
        ],
        'longCache' => [
            'adapter' => Redis::class,
            'options' => [
                'ttl'       => 600,
                'server'    => [
                    'host'    => 'redis',
                    'port'    => 6379,
                    'timeout' => 10,
                ],
                'namespace' => 'LONG',
            ],
        ],
    ],
];
