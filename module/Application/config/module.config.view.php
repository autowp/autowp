<?php

namespace Application;

use Zend\Permissions\Acl\Acl;

use Autowp\Image;

return [
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'forbidden_template'       => 'error/403',
        'exception_template'       => 'error/index',
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/403'               => __DIR__ . '/../view/error/403.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'page'              => View\Helper\Page::class,
            'pageTitle'         => View\Helper\PageTitle::class,
            'breadcrumbs'       => View\Helper\Breadcrumbs::class,
            'humanTime'         => View\Helper\HumanTime::class,
            'markdown'          => View\Helper\Markdown::class,
            'pastTimeIndicator' => View\Helper\PastTimeIndicator::class,
            'img'               => View\Helper\Img::class,
            'pictures'          => View\Helper\Pictures::class,
            'moderMenu'         => View\Helper\ModerMenu::class,
            'count'             => View\Helper\Count::class,
            View\Helper\FormElement::class => Form\View\Helper\FormElement::class,
            'form_element'                 => Form\View\Helper\FormElement::class,
            'formelement'                  => Form\View\Helper\FormElement::class,
            'formElement'                  => Form\View\Helper\FormElement::class,
            'FormElement'                  => Form\View\Helper\FormElement::class,
            'formpicturemulticheckbox'     => Form\View\Helper\FormPictureMultiCheckbox::class
        ],
        'factories' => [
            'car' => function ($sm) {
                return new View\Helper\Car(
                    $sm->get(VehicleNameFormatter::class)
                );
            },
            'pic' => function($sm) {
                return new View\Helper\Pic(
                    $sm->get(PictureNameFormatter::class)
                );
            },
            'pageEnv' => function($sm) {
                $language = $sm->get(Language::class);
                return new View\Helper\PageEnv($language);
            },
            'mainMenu' => function($sm) {
                return new View\Helper\MainMenu($sm->get(MainMenu::class));
            },
            'language' => function($sm) {
                return new View\Helper\Language($sm->get(Language::class));
            },
            'languagePicker' => function($sm) {
                $languagePicker = $sm->get(LanguagePicker::class);
                return new View\Helper\LanguagePicker($languagePicker);
            },
            'user' => function($sm) {
                $acl = $sm->get(Acl::class);
                return new View\Helper\User($acl);
            },
            'fileSize' => function($sm) {
                return new View\Helper\FileSize(
                    $sm->get(Language::class),
                    $sm->get(FileSize::class)
                );
            },
            'humanDate' => function($sm) {
                $language = $sm->get(Language::class);
                return new View\Helper\HumanDate($language->getLanguage());
            },
            'comments' => function($sm) {
                $commentForm = $sm->get('CommentForm');
                return new View\Helper\Comments($commentForm);
            },
            'userText' => function($sm) {
                $router = $sm->get('Router');
                return new View\Helper\UserText($router);
            },
            'imageStorage' => function($sm) {
                $imageStorage = $sm->get(Image\Storage::class);
                return new View\Helper\ImageStorage($imageStorage);
            },
                'inlinePicture' => function($sm) {
                return new View\Helper\InlinePicture($translator);
            },
            'sidebar' => function($sm) {
                return new View\Helper\Sidebar(
                    $sm->get(Model\Message::class)
                );
            }
        ]
    ],
    'view_helper_config' => [
        'flashmessenger' => [
            'message_open_format'      => '<div%s><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><ul><li>',
            'message_close_string'     => '</li></ul></div>',
            'message_separator_string' => '</li><li>'
        ]
    ],
];