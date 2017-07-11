<?php

namespace Application;

use Zend_Db_Adapter_Abstract;

use PDO;

return [
    'zf1db' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'host'     => getenv('AUTOWP_DB_HOST'),
            'username' => getenv('AUTOWP_DB_USERNAME'),
            'password' => getenv('AUTOWP_DB_PASSWORD'),
            'dbname'   => getenv('AUTOWP_DB_DBNAME'),
            'charset'  => 'utf8',
            'driver_options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'"
            ]
        ],
        'isDefaultTableAdapter' => true,
        'defaultMetadataCache'  => 'fast'
    ],
    'db' => [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => getenv('AUTOWP_DB_HOST'),
        'charset'        => 'utf8',
        'dbname'         => getenv('AUTOWP_DB_DBNAME'),
        'username'       => getenv('AUTOWP_DB_USERNAME'),
        'password'       => getenv('AUTOWP_DB_PASSWORD'),
        'driver_options' => [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'"
        ],
    ],
    'service_manager' => [
        'factories' => [
            Zend_Db_Adapter_Abstract::class => Service\ZF1DbAdapterFactory::class
        ]
    ]
];
