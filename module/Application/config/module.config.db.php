<?php

namespace Application;

use Zend_Db_Adapter_Abstract;

use PDO;

return [
    'zf1db' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'host'     => '',
            'username' => '',
            'password' => '',
            'dbname'   => '',
            'charset'  => 'utf8'
        ],
        'isDefaultTableAdapter' => true,
        'defaultMetadataCache'  => 'fast'
    ],
    'db' => [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => '',
        'charset'        => 'utf8',
        'dbname'         => '',
        'username'       => '',
        'password'       => ''
    ],
    'service_manager' => [
        'factories' => [
            Zend_Db_Adapter_Abstract::class => Service\ZF1DbAdapterFactory::class
        ]
    ]
];
