<?php

namespace Application;

return [
    'rabbitmq'        => [
        'host'     => 'rabbitmq',
        'port'     => 5672,
        'user'     => 'guest',
        'password' => 'guest',
        'vhost'    => '/',
    ],
    'service_manager' => [
        'factories' => [
            'RabbitMQ' => Service\RabbitMQFactory::class,
        ],
    ],
];
