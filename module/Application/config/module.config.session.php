<?php

namespace Application;

use Zend\Session\SaveHandler\SaveHandlerInterface;

use Zend_Db_Adapter_Abstract;

return [
    'service_manager' => [
        'factories' => [
            SaveHandlerInterface::class => function($sm) {

                $db = $sm->get(Zend_Db_Adapter_Abstract::class);

                return new Session\SaveHandler\DbTable([
                    'table' => [
                        'db'      => $db,
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