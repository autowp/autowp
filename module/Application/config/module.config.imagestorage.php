<?php

namespace Application;

$imageDir = __DIR__ . '/../../../public_html/image/';

$host = getenv('AUTOWP_HOST');

return [
    'imageStorage' => [
        'imageTableName' => 'image',
        'formatedImageTableName' => 'formated_image',
        'fileMode' => 0644,
        'dirMode' => 0755,

        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
                'url'  => 'http://i.' . $host . '/image/format/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ],
                'bucket' => getenv('AUTOWP_IMAGE_FORMAT_BUCKET')
            ],
            'museum' => [
                'path' => $imageDir . "museum",
                'url'  => 'http://i.' . $host . '/image/museum/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ],
                'bucket' => getenv('AUTOWP_IMAGE_MUSEUM_BUCKET')
            ],
            'user' => [
                'path' => $imageDir . "user",
                'url'  => 'http://i.' . $host . '/image/user/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ],
                'bucket' => getenv('AUTOWP_IMAGE_USER_BUCKET')
            ],
            'brand' => [
                'path' => $imageDir . "brand",
                'url'  => 'http://i.' . $host . '/image/brand/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ],
                'bucket' => getenv('AUTOWP_IMAGE_BRAND_BUCKET')
            ],
            'picture' => [
                'path' => __DIR__ . '/../../../public_html/pictures/',
                'url'  => 'http://i.' . $host . '/pictures/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ],
                'bucket' => getenv('AUTOWP_IMAGE_PICTURE_BUCKET')
            ]
        ],

        'formatedImageDirName' => 'format',

        'formats' => [
            'format9'    => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => 1
            ],
            'icon' => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'logo' => [
                'fitType'    => 1,
                'width'      => 120,
                'height'     => 120,
                'background' => '#F5F5F5',
                'strip'      => 1
            ],
            'photo' => [
                'fitType'    => 2,
                'width'      => 555,
                'height'     => 400,
                'background' => 'transparent',
                'reduceOnly' => 1,
                'strip'      => 1
            ],
            'avatar' => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'brandicon' => [
                'fitType'    => 1,
                'width'      => 70,
                'height'     => 70,
                'background' => '#EDE9DE',
                'strip'      => 1
            ],
            'brandicon2' => [
                'fitType'    => 2,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'picture-thumb' => [
                'fitType'          => 0,
                'width'            => 155,
                'height'           => 116,
                'strip'            => 1,
                'format'           => 'jpeg',
                'proportionalCrop' => 1,
                'background'       => '#fff'
            ],
            'picture-thumb-medium' => [
                'fitType'          => 0,
                'width'            => 350,
                'height'           => 270,
                'strip'            => 1,
                'format'           => 'jpeg',
                'proportionalCrop' => 1
            ],
            'picture-thumb-large' => [
                'fitType'          => 0,
                'width'            => 620,
                'height'           => 464,
                'strip'            => 1,
                'format'           => 'jpeg',
                'proportionalCrop' => 1
            ],
            'picture-medium' => [
                'fitType' => 0,
                'width'   => 350,
                'strip'   => 1,
                'format'  => 'jpeg'
            ],
            'picture-preview-large' => [
                'fitType' => 0,
                'width'   => 538,
                'strip'   => 1,
                'format'  => 'jpeg'
            ],
            'picture-gallery' => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => 1,
                'strip'      => 1,
                'format'     => 'jpeg'
            ],
            'picture-gallery-full' => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => 1,
                'ignoreCrop' => 1,
                'strip'      => 1,
                'format'     => 'jpeg'
            ]
        ],
        'formatToS3' => true,
        's3' => [
            'region' => '',
            'version' => 'latest',
            'endpoint' => getenv('AUTOWP_S3_ENDPOINT'),
            'credentials' => [
                'key' => getenv('AUTOWP_S3_KEY'),
                'secret' => getenv('AUTOWP_S3_SECRET')
            ],
            'use_path_style_endpoint' => true
        ]
    ]
];
