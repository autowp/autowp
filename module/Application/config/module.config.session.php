<?php

namespace Application;

use Zend\Session\SaveHandler\SaveHandlerInterface;
use Zend\Session\Storage\SessionArrayStorage;

return [
    'service_manager' => [
        'factories' => [
            SaveHandlerInterface::class => Session\Service\SaveHandlerCacheFactory::class
            //Session\Service\SaveHandlerDbTableFactory::class
        ]
    ],
    'session_config' => [
        'use_cookies'         => true,
        'gc_maxlifetime'      => 864000,
        'remember_me_seconds' => 864000,
        'cookie_httponly'     => false,
        'cookie_domain'       => '.wheelsage.org',
        'name'                => 'sid'
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ]
];
