<?php

namespace Application;

return [
    'view_manager'       => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'forbidden_template'       => 'error/403',
        'exception_template'       => 'error/index',
        'template_map'             => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/403'               => __DIR__ . '/../view/error/403.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack'      => [
            __DIR__ . '/../view',
        ],
        'strategies'               => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers'       => [
        'invokables' => [
            'pageTitle' => View\Helper\PageTitle::class,
            'markdown'  => View\Helper\Markdown::class,
            'count'     => View\Helper\Count::class,
            'favicons'  => View\Helper\Favicons::class,
        ],
        'factories'  => [
            'car'           => View\Helper\Service\CarFactory::class,
            'fileSize'      => View\Helper\Service\FileSizeFactory::class,
            'hostManager'   => View\Helper\Service\HostManagerFactory::class,
            'img'           => View\Helper\Service\ImgFactory::class,
            'inlinePicture' => View\Helper\Service\InlinePictureFactory::class,
            'language'      => View\Helper\Service\LanguageFactory::class,
            'page'          => View\Helper\Service\PageFactory::class,
            'pic'           => View\Helper\Service\PicFactory::class,
            'userText'      => View\Helper\Service\UserTextFactory::class,
        ],
    ],
    'view_helper_config' => [],
];
