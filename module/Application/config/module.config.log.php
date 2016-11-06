<?php

namespace Application;

return [
    'log' => [
        'ErrorLog' => [
            'writers' => [
                [
                    'name' => 'stream',
                    'priority' => \Zend\Log\Logger::ERR,
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
