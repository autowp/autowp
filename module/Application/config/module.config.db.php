<?php

namespace Application;

use PDO;

return [
    'db' => [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => 'mysql',
        'charset'        => 'utf8',
        'dbname'         => 'autowp',
        'username'       => 'autowp',
        'password'       => 'password',
        'driver_options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'",
        ],
    ],
];
