<?php

namespace Application;

use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\ExternalLoginService\Factory as ExternalLoginServiceFactory;

use Zend_Cache_Manager;
use Zend_Db_Adapter_Abstract;

return [
    'controllers' => [
        'factories' => [
            Controller\AboutController::class           => Controller\Frontend\Service\AboutControllerFactory::class,
            Controller\AccountController::class         => Controller\Frontend\Service\AccountControllerFactory::class,
            Controller\ArticlesController::class        => InvokableFactory::class,
            Controller\BrandsController::class          => Controller\Frontend\Service\BrandsControllerFactory::class,
            Controller\CarsController::class            => Controller\Frontend\Service\CarsControllerFactory::class,
            Controller\CatalogueController::class       => Controller\Frontend\Service\CatalogueControllerFactory::class,
            Controller\CategoryController::class        => Controller\Frontend\Service\CategoryControllerFactory::class,
            Controller\ChartController::class           => Controller\Frontend\Service\ChartControllerFactory::class,
            Controller\CommentsController::class        => Controller\Frontend\Service\CommentsControllerFactory::class,
            Controller\DonateController::class          => InvokableFactory::class,
            Controller\FactoriesController::class       => Controller\Frontend\Service\FactoriesControllerFactory::class,
            Controller\FeedbackController::class        => Controller\Frontend\Service\FeedbackControllerFactory::class,
            Controller\ForumsController::class          => Controller\Frontend\Service\ForumsControllerFactory::class,
            Controller\IndexController::class           => Controller\Frontend\Service\IndexControllerFactory::class,
            Controller\InboxController::class           => InvokableFactory::class,
            Controller\InfoController::class            => Controller\Frontend\Service\InfoControllerFactory::class,
            Controller\LogController::class             => InvokableFactory::class,
            Controller\LoginController::class           => Controller\Frontend\Service\LoginControllerFactory::class,
            Controller\MapController::class             => InvokableFactory::class,
            Controller\MostsController::class           => Controller\Frontend\Service\MostsControllerFactory::class,
            Controller\NewController::class             => InvokableFactory::class,
            Controller\MuseumsController::class         => InvokableFactory::class,
            Controller\PerspectiveController::class     => InvokableFactory::class,
            Controller\PictureController::class         => InvokableFactory::class,
            Controller\PictureFileController::class     => InvokableFactory::class,
            Controller\PulseController::class           => InvokableFactory::class,
            Controller\RegistrationController::class    => Controller\Frontend\Service\RegistrationControllerFactory::class,
            Controller\RestorePasswordController::class => Controller\Frontend\Service\RestorePasswordControllerFactory::class,
            Controller\DocController::class             => InvokableFactory::class,
            Controller\TelegramController::class        => Controller\Frontend\Service\TelegramControllerFactory::class,
            Controller\TwinsController::class           => Controller\Frontend\Service\TwinsControllerFactory::class,
            Controller\UsersController::class           => Controller\Frontend\Service\UsersControllerFactory::class,
            Controller\UploadController::class          => Controller\Frontend\Service\UploadControllerFactory::class,
            Controller\VotingController::class          => InvokableFactory::class,
            Controller\Api\ContactsController::class    => InvokableFactory::class,
            Controller\Api\PictureController::class     => Controller\Api\Service\PictureControllerFactory::class,
            Controller\Api\UsersController::class       => InvokableFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'catalogue'       => Controller\Plugin\Catalogue::class,
            'log'             => Controller\Plugin\Log::class,
            'pictureVote'     => Controller\Plugin\PictureVote::class,
            'forbiddenAction' => Controller\Plugin\ForbiddenAction::class
        ],
        'factories' => [
            'car'       => Controller\Plugin\Service\CarFactory::class,
            'fileSize'  => Controller\Plugin\Service\FileSizeFactory::class,
            'language'  => Controller\Plugin\Service\LanguageFactory::class,
            'oauth2'    => Factory\OAuth2PluginFactory::class,
            'pic'       => Controller\Plugin\Service\PicFactory::class,
            'sidebar'   => Controller\Plugin\Service\SidebarFactory::class,
            'translate' => Controller\Plugin\Service\TranslateFactory::class,
        ]
    ],
    'translator' => [
        'locale' => 'ru',
        'fallbackLocale' => 'en',
        'translation_file_patterns' => [
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.php'
            ],
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => \Zend\I18n\Translator\Resources::getBasePath(),
                'pattern'  => \Zend\I18n\Translator\Resources::getPatternForValidator()
            ],
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => \Zend\I18n\Translator\Resources::getBasePath(),
                'pattern'  => \Zend\I18n\Translator\Resources::getPatternForCaptcha()
            ]
        ],
    ],
    'db' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'host'     => '',
            'username' => '',
            'password' => '',
            'dbname'   => '',
            'charset'  => 'utf8'
        ],
        'isDefaultTableAdapter' => true,
        'defaultMetadataCache'  => 'fast',
        'params.driver_options.1002' => "set time_zone = 'UTC'"
    ],
    'service_manager' => [
        'factories' => [
            Acl::class                           => Permissions\AclFactory::class,
            ExternalLoginServiceFactory::class   => Service\ExternalLoginServiceFactory::class,
            FileSize::class                      => InvokableFactory::class,
            HostManager::class                   => Service\HostManagerFactory::class,
            Language::class                      => Service\LanguageFactory::class,
            LanguagePicker::class                => Service\LanguagePickerFactory::class,
            MainMenu::class                      => Service\MainMenuFactory::class,
            Model\BrandNav::class                => Model\Service\BrandNavFactory::class,
            Model\BrandVehicle::class            => Model\Service\BrandVehicleFactory::class,
            Model\DbTable\Picture::class         => Model\Service\DbTablePictureFactory::class,
            Model\Message::class                 => Model\Service\MessageFactory::class,
            PictureNameFormatter::class          => Service\PictureNameFormatterFactory::class,
            Service\SpecificationsService::class => Service\SpecificationsServiceFactory::class,
            Service\TelegramService::class       => Service\TelegramServiceFactory::class,
            Service\UsersService::class          => Service\UsersServiceFactory::class,
            VehicleNameFormatter::class          => Service\VehicleNameFormatterFactory::class,
            Zend_Cache_Manager::class            => Service\ZF1CacheManagerFactory::class,
            Zend_Db_Adapter_Abstract::class      => Service\ZF1DbAdapterFactory::class,
            'translator'                         => \Zend\Mvc\I18n\TranslatorFactory::class,
            Model\CarOfDay::class                => Model\Service\CarOfDayFactory::class
        ],
        'aliases' => [
            'ZF\OAuth2\Provider\UserId' => Provider\UserId\OAuth2UserIdProvider::class
        ],
        'abstract_factories' => [
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            //'Zend\Form\FormAbstractServiceFactory',
        ],
    ],

    'telegram' => [
        'accessToken' => '',
        'token'       => '',
        'webhook'     => ''
    ],

    'twitter' => [
        'username' => '',
        'oauthOptions' => [
            'consumerKey'    => '',
            'consumerSecret' => ''
        ],
        'token' => [
            'oauth_token'        => '',
            'oauth_token_secret' => ''
        ]
    ],

    'hosts' => [
        'ru' => [
            'hostname' => 'www.autowp.ru',
            'timezone' => 'Europe/Moscow',
            'name'     => 'Русский',
            'flag'     => 'flag-icon flag-icon-ru',
            'cookie'   => '.autowp.ru'
        ],
        'en' => [
            'hostname' => 'en.wheelsage.org',
            'timezone' => 'Europe/London',
            'name'     => 'English (beta)',
            'flag'     => 'flag-icon flag-icon-gb',
            'cookie'   => '.wheelsage.org'
        ],
        'fr' => [
            'hostname' => 'fr.wheelsage.org',
            'timezone' => 'Europe/Paris',
            'name'     => 'Français (beta)',
            'flag'     => 'flag-icon flag-icon-fr',
            'cookie'   => '.wheelsage.org'
        ],
        'zh' => [
            'hostname' => 'zh.wheelsage.org',
            'timezone' => 'Asia/Beijing',
            'name'     => '中文 (beta)',
            'flag'     => 'flag-icon flag-icon-cn',
            'cookie'   => '.wheelsage.org'
        ]
    ],

    /*'acl' => [
        'cache'         => 'long',
        'cacheLifetime' => 3600
    ],*/

    'textstorage' => [
        'textTableName'     => 'textstorage_text',
        'revisionTableName' => 'textstorage_revision',
        'dbAdapter'         => Zend_Db_Adapter_Abstract::class
    ],

    'feedback' => [
        'from'     => 'no-reply@autowp.ru',
        'fromname' => 'robot autowp.ru',
        'to'       => 'autowp@gmail.com',
        'subject'  => 'AutoWP Feedback'
    ],

    'validators' => [
        'factories' => [
            Validator\Brand\NameNotExists::class => InvokableFactory::class,
            Validator\User\EmailExists::class    => InvokableFactory::class,
            Validator\User\EmailNotExists::class => InvokableFactory::class,
            Validator\User\Login::class          => InvokableFactory::class,
        ],
    ],

    'externalloginservice' => [
        'vk' => [
            'clientId'     => '',
            'clientSecret' => ''
        ],
        'google-plus' => [
            'clientId'     => '',
            'clientSecret' => ''
        ],
        'twitter' => [
            'consumerKey'    => '',
            'consumerSecret' => ''
        ],
        'facebook' => [
            'clientId'     => '',
            'clientSecret' => '',
            'scope'        => ['public_profile', 'user_friends']
        ],
        'github' => [
            'clientId'     => '',
            'clientSecret' => ''
        ],
        'linkedin' => [
            'clientId'     => '',
            'clientSecret' => ''
        ]
    ],

    'gulp-rev' => [
        'manifest' => __DIR__ . '/../../../public_html/rev-manifest.json',
        'prefix'   => '/'
    ]
];
