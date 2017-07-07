<?php

namespace Application;

$imageDir = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR;

return [
    'zf1db' => [
        'params' => [
            'host'     => 'autowp_test_mysql',
            'username' => 'autowp_test',
            'password' => 'test',
            'dbname'   => 'autowp_test',
            'driver_options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'"
            ]
        ],
        'defaultMetadataCache' => null
    ],
    'db' => [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => 'autowp_test_mysql',
        'charset'        => 'utf8',
        'dbname'         => 'autowp_test',
        'username'       => 'autowp_test',
        'password'       => 'test',
        'driver_options' => [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'"
        ],
    ],
    'users' => [
        'salt'      => 'users-salt',
        'emailSalt' => 'email-salt'
    ],
    'mail' => [
        'transport' => [
            'type' => 'in-memory'
        ],
    ],
    'cachemanager' => [
        'fast' => [
            /*'caching' => false,
            'write_control' => false,*/
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 1800,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'black-hole'
            ]
        ]
    ],
    'caches' => [
        'fastCache' => [
            'adapter' => [
                'name'     => 'blackHole',
                'lifetime' => 180,
            ],
        ],
        'longCache' => [
            'adapter' => [
                'name'     => 'blackHole',
                'lifetime' => 600
            ],
        ],
        'localeCache' => [
            'adapter' => [
                'name'     => 'blackHole',
                'lifetime' => 600
            ],
        ],
    ],
    'imageStorage' => [
        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
            ],
            'museum' => [
                'path' => $imageDir . "museum",
            ],
            'user' => [
                'path' => $imageDir . "user",
            ],
            'brand' => [
                'path' => $imageDir . "brand",
            ],
            'picture' => [
                'path' => __DIR__ . '/pictures/',
            ]
        ],
    ],
    'forms' => [
        'FeedbackForm' => [
            'elements' => [
                'captcha' => [
                    'spec' => [
                        'options' => [
                            'captcha' => [
                                'imgDir' => sys_get_temp_dir()
                            ]
                        ],
                    ],
                ],
            ]
        ],
        'RegistrationForm' => [
            'elements' => [
                'captcha' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'captcha'
                    ],
                ]
            ],
            'input_filter' => [
                'captcha' => [
                    'required' => false
                ]
            ]
        ],
    ],
    'mosts_min_vehicles_count' => 1,

    'hosts' => [
        'ru' => [
            'hostname' => 'localhost',
            'cookie'   => ''
        ],
        'en' => [
            'hostname' => 'localhost',
            'cookie'   => ''
        ],
        'fr' => [
            'hostname' => 'localhost',
            'cookie'   => ''
        ],
        'zh' => [
            'hostname' => 'localhost',
            'cookie'   => ''
        ],
    ],

    'hostname_whitelist' => ['localhost'],
];
