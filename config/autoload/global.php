<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

use Application\Provider\UserId\OAuth2UserIdProvider;
use Application\Provider\UserId\OAuth2UserIdProviderFactory;

return [
    'service_manager' => [
        'aliases' => [
            'ZF\OAuth2\Provider\UserId' => OAuth2UserIdProvider::class
        ],
        'factories' => [
            OAuth2UserIdProvider::class => OAuth2UserIdProviderFactory::class,
        ]
    ]
];
