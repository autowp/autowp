<?php

return [
    'db' => [
        'params' => [
            'host'     => 'localhost',
            'username' => 'autowp_test',
            'password' => 'test',
            'dbname'   => 'autowp_test',
        ],
        'defaultMetadataCache' => null,
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
];
