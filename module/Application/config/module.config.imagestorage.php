<?php

namespace Application;

use function explode;
use function getenv;

return [
    'imageStorage' => [
        'imageTableName'         => 'image',
        'formatedImageTableName' => 'formated_image',
        'dirs'                   => [
            'format'  => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => getenv('AUTOWP_IMAGE_FORMAT_BUCKET'),
            ],
            'museum'  => [
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2,
                    ],
                ],
                'bucket'         => getenv('AUTOWP_IMAGE_MUSEUM_BUCKET'),
            ],
            'user'    => [
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2,
                    ],
                ],
                'bucket'         => getenv('AUTOWP_IMAGE_USER_BUCKET'),
            ],
            'brand'   => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => getenv('AUTOWP_IMAGE_BRAND_BUCKET'),
            ],
            'picture' => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => getenv('AUTOWP_IMAGE_PICTURE_BUCKET'),
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
            'endpoint'                => explode(';', getenv('AUTOWP_S3_ENDPOINT')),
            'credentials'             => [
                'key'    => getenv('AUTOWP_S3_KEY'),
                'secret' => getenv('AUTOWP_S3_SECRET'),
            ],
            'use_path_style_endpoint' => true,
        ],
    ],
];
