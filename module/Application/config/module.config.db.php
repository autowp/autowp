<?php

namespace Application;

return [
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
    ]
];
