<?php

namespace Application;

use Laminas\Log\Logger;

return [
    'log' => [
        'ErrorLog' => [
            'writers' => [
                [
                    'name' => 'stream',
                    'priority' => Logger::ERR,
                    'options' => [
                        'stream' => __DIR__ . '/../../../logs/zf-error.log',
                        'processors' => [
                            [
                                'name' => Log\Processor\Url::class
                            ]
                        ]
                    ],
                ],
            ],
        ],
    ],
];
