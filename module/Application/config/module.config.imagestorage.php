<?php

namespace Application;

$imageDir = APPLICATION_PATH . "/../public_html/image/";

return [
    'imageStorage' => [
        'imageTableName' => 'image',
        'formatedImageTableName' => 'formated_image',
        'fileMode' => 0644,
        'dirMode' => 0755,

        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
                'url'  => 'http://i.wheelsage.org/image/format/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
            'museum' => [
                'path' => $imageDir . "museum",
                'url'  => 'http://i.wheelsage.org/image/museum/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ]
            ],
            'user' => [
                'path' => $imageDir . "user",
                'url'  => 'http://i.wheelsage.org/image/user/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ]
            ],
            'brand' => [
                'path' => $imageDir . "brand",
                'url'  => 'http://i.wheelsage.org/image/brand/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
            'picture' => [
                'path' => APPLICATION_PATH . "/../public_html/pictures/",
                'url'  => 'http://i.wheelsage.org/pictures/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
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
            'picture-medium' => [
                'fitType' => 0,
                'width'   => 350,
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
        ]
    ]
];
