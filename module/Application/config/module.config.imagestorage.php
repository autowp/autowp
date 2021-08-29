<?php

declare(strict_types=1);

namespace Application;

return [
    'imageStorage' => [
        'imageTableName'         => 'image',
        'formatedImageTableName' => 'formated_image',
        'dirs'                   => [
            'format'  => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => 'format',
            ],
            'user'    => [
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2,
                    ],
                ],
                'bucket'         => 'user',
            ],
            'brand'   => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => 'brand',
            ],
            'picture' => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => 'picture',
            ],
        ],
        'formatedImageDirName'   => 'format',
        'formats'                => [
            'format9'               => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => true,
            ],
            'icon'                  => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => true,
            ],
            'logo'                  => [
                'fitType'    => 1,
                'width'      => 120,
                'height'     => 120,
                'background' => '#F5F5F5',
                'strip'      => true,
            ],
            'photo'                 => [
                'fitType'    => 2,
                'width'      => 555,
                'height'     => 400,
                'background' => 'transparent',
                'reduceOnly' => true,
                'strip'      => true,
            ],
            'avatar'                => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => true,
            ],
            'brandicon'             => [
                'fitType'    => 1,
                'width'      => 70,
                'height'     => 70,
                'background' => '#EDE9DE',
                'strip'      => true,
            ],
            'brandicon2'            => [
                'fitType'    => 2,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => true,
            ],
            'picture-thumb'         => [
                'fitType'          => 0,
                'width'            => 155,
                'height'           => 116,
                'strip'            => true,
                'format'           => 'jpeg',
                'proportionalCrop' => true,
                'background'       => '#fff',
            ],
            'picture-thumb-medium'  => [
                'fitType'          => 0,
                'width'            => 350,
                'height'           => 270,
                'strip'            => true,
                'format'           => 'jpeg',
                'proportionalCrop' => true,
            ],
            'picture-thumb-large'   => [
                'fitType'          => 0,
                'width'            => 620,
                'height'           => 464,
                'strip'            => true,
                'format'           => 'jpeg',
                'proportionalCrop' => true,
            ],
            'picture-medium'        => [
                'fitType' => 0,
                'width'   => 350,
                'strip'   => true,
                'format'  => 'jpeg',
            ],
            'picture-preview-large' => [
                'fitType' => 0,
                'width'   => 538,
                'strip'   => true,
                'format'  => 'jpeg',
            ],
            'picture-gallery'       => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => true,
                'strip'      => true,
                'format'     => 'jpeg',
            ],
            'picture-gallery-full'  => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => true,
                'ignoreCrop' => true,
                'strip'      => true,
                'format'     => 'jpeg',
            ],
        ],
        's3'                     => [
            'region'                  => '',
            'version'                 => 'latest',
            'endpoint'                => 'http://minio:9000',
            'credentials'             => [
                'key'    => 'key',
                'secret' => 'secret',
            ],
            'use_path_style_endpoint' => true,
        ],
        'srcOverride'            => [],
    ],
];
