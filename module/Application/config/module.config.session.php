<?php

namespace Application;

use Zend\Session\SaveHandler\SaveHandlerInterface;

return [
    'service_manager' => [
        'factories' => [
            SaveHandlerInterface::class => function($sm) {
                return new Session\SaveHandler\DbTable([
                    'table' => [
                        'name'    => 'session',
                        'primary' => ['id']
                    ]
                ]);
            },
        ]
    ],
    'session_config' => [
        'use_cookies'         => true,
        'gc_maxlifetime'      => 864000,
        'remember_me_seconds' => 864000,
        'cookie_httponly'     => false,
        'cookie_domain'       => '.wheelsage.org',
    ],
    'session_storage' => [
        'type' => \Zend\Session\Storage\SessionArrayStorage::class
    ]
];