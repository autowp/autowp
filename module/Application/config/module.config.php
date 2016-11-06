<?php

namespace Application;

use Zend\Mail\Transport\TransportInterface as MailTransport;
use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\ExternalLoginService\Factory as ExternalLoginServiceFactory;
use Autowp\Image;
use Autowp\TextStorage;

use Application\Model\DbTable\Picture;

use Zend_Application_Resource_Cachemanager;
use Zend_Application_Resource_Db;
use Zend_Cache_Manager;
use Zend_Db_Adapter_Abstract;

return [
    'controllers' => [
        'factories' => [
            Controller\AboutController::class => function($sm) {
                $acl = $sm->get(Acl::class);
                return new Controller\AboutController($acl);
            },
            Controller\AccountController::class => function($sm) {
                $config = $sm->get('Config');
                return new Controller\AccountController(
                    $sm->get(Service\UsersService::class),
                    $sm->get('MvcTranslator'),
                    $sm->get('AccountEmailForm'),
                    $sm->get('AccountProfileForm'),
                    $sm->get('AccountSettingsForm'),
                    $sm->get('AccountPhotoForm'),
                    $sm->get('ChangePasswordForm'),
                    $sm->get('DeleteUserForm'),
                    $sm->get(ExternalLoginServiceFactory::class),
                    $config['hosts'],
                    $sm->get(Service\SpecificationsService::class),
                    $sm->get(Model\Message::class)
                );
            },
            Controller\ArticlesController::class     => InvokableFactory::class,
            Controller\BanController::class          => InvokableFactory::class,
            Controller\BrandsController::class => function($sm) {
                $cache = $sm->get('longCache');
                return new Controller\BrandsController($cache);
            },
            Controller\CarsController::class => function($sm) {
                return new Controller\CarsController(
                    $sm->get(HostManager::class),
                    $sm->get('AttrsLogFilterForm'),
                    $sm->get(Service\SpecificationsService::class),
                    $sm->get(Model\Message::class)
                );
            },
            Controller\CatalogueController::class => function($sm) {
                return new Controller\CatalogueController(
                    $sm->get(TextStorage\Service::class),
                    $sm->get('longCache'),
                    $sm->get(Service\SpecificationsService::class),
                    $sm->get(Model\BrandVehicle::class)
                );
            },
            Controller\CategoryController::class => function($sm) {
                $cache = $sm->get('longCache');
                return new Controller\CategoryController($cache);
            },
            Controller\ChartController::class => function($sm) {
                return new Controller\ChartController(
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            Controller\CommentsController::class => function($sm) {
                return new Controller\CommentsController(
                    $sm->get(HostManager::class),
                    $sm->get('CommentForm'),
                    $sm->get(Model\Message::class)
                );
            },
            Controller\DonateController::class       => InvokableFactory::class,
            Controller\FactoriesController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                return new Controller\FactoriesController($textStorage);
            },
            Controller\FeedbackController::class     => function($sm) {
                $form = $sm->get('FeedbackForm');
                $transport = $sm->get(MailTransport::class);
                $options = $sm->get('Config')['feedback'];
                return new Controller\FeedbackController($form, $transport, $options);
            },
            Controller\ForumsController::class => function($sm) {
                return new Controller\ForumsController(
                    $sm->get('ForumsTopicNewForm'),
                    $sm->get('CommentForm'),
                    $sm->get(MailTransport::class),
                    $sm->get('MvcTranslator'),
                    $sm->get(Model\Message::class)
                );
            },
            Controller\IndexController::class => function($sm) {
                return new Controller\IndexController(
                    $sm->get('fastCache'),
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            Controller\InboxController::class        => InvokableFactory::class,
            Controller\InfoController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                return new Controller\InfoController($textStorage);
            },
            Controller\LogController::class          => InvokableFactory::class,
            Controller\LoginController::class => function($sm) {
                $service = $sm->get(Service\UsersService::class);
                $form = $sm->get('LoginForm');
                $translator = $sm->get('MvcTranslator');
                $externalLoginFactory = $sm->get(ExternalLoginServiceFactory::class);
                $config = $sm->get('Config');

                return new Controller\LoginController($service, $form, $translator, $externalLoginFactory, $config['hosts']);
            },
            Controller\MapController::class          => InvokableFactory::class,
            Controller\MostsController::class => function($sm) {
                return new Controller\MostsController(
                    $sm->get(TextStorage\Service::class),
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            Controller\NewController::class          => InvokableFactory::class,
            Controller\MuseumsController::class      => InvokableFactory::class,
            Controller\PerspectiveController::class  => InvokableFactory::class,
            Controller\PictureController::class      => InvokableFactory::class,
            Controller\PictureFileController::class  => InvokableFactory::class,
            Controller\PulseController::class        => InvokableFactory::class,
            Controller\RegistrationController::class => function($sm) {
                $service = $sm->get(Service\UsersService::class);
                $form = $sm->get('RegistrationForm');
                return new Controller\RegistrationController($service, $form);
            },
            Controller\RestorePasswordController::class => function($sm) {
                $service = $sm->get(Service\UsersService::class);
                $restoreForm = $sm->get('RestorePasswordForm');
                $newPasswordForm = $sm->get('NewPasswordForm');
                $transport = $sm->get(MailTransport::class);
                return new Controller\RestorePasswordController($service, $restoreForm, $newPasswordForm, $transport);
            },
            Controller\DocController::class => InvokableFactory::class,
            Controller\TelegramController::class => function($sm) {
                $service = $sm->get(Service\TelegramService::class);
                return new Controller\TelegramController($service);
            },
            Controller\TwinsController::class => function($sm) {
                return new Controller\TwinsController(
                    $sm->get(TextStorage\Service::class),
                    $sm->get('longCache'),
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            Controller\UsersController::class => function($sm) {
                $cache = $sm->get('longCache');
                return new Controller\UsersController($cache);
            },
            Controller\UploadController::class => function($sm) {
                $partial = $sm->get('ViewHelperManager')->get('partial');
                $telegram = $sm->get(Service\TelegramService::class);
                return new Controller\UploadController($partial, $telegram);
            },
            Controller\VotingController::class       => InvokableFactory::class,
            Controller\Api\ContactsController::class => InvokableFactory::class,
            Controller\Api\PictureController::class  => InvokableFactory::class,
            Controller\Api\UsersController::class    => InvokableFactory::class,
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
            'car' => function ($sm) {
                return new Controller\Plugin\Car(
                    $sm->get(TextStorage\Service::class),
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            'fileSize' => function($sm) {
                return new Controller\Plugin\FileSize(
                    $sm->get(Language::class),
                    $sm->get(FileSize::class)
                );
            },
            'language' => function($sm) {
                $language = $sm->get(Language::class);
                return new Controller\Plugin\Language($language);
            },
            'oauth2' => Factory\OAuth2PluginFactory::class,
            'pic' => function($sm) {
                return new Controller\Plugin\Pic(
                    $sm->get(TextStorage\Service::class),
                    $sm->get('MvcTranslator'),
                    $sm->get(PictureNameFormatter::class),
                    $sm->get(Service\SpecificationsService::class)
                );
            },
            'sidebar' => function ($sm) {
                return new Controller\Plugin\Sidebar(
                    $sm->get('fastCache'),
                    $sm->get('MvcTranslator')
                );
            },
            'translate' => function ($sm) {
                $translator = $sm->get('MvcTranslator');
                return new Controller\Plugin\Translate($translator);
            },
            'user' => function($sm) {
                $acl = $sm->get(Acl::class);
                $config = $sm->get('Config');
                return new Controller\Plugin\User($acl, $config['hosts']);
            },
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
            Model\Message::class => function($sm) {
                return new Model\Message(
                    $sm->get(Service\TelegramService::class)
                );
            },
            Model\BrandVehicle::class => function($sm) {
                $config = $sm->get('Config');
                return new Model\BrandVehicle(array_keys($config['hosts']));
            },
            VehicleNameFormatter::class => function($sm) {
                return new VehicleNameFormatter(
                    $sm->get('translator'),
                    $sm->get('ViewRenderer')
                );
            },
            PictureNameFormatter::class => function($sm) {
                return new PictureNameFormatter(
                    $sm->get('MvcTranslator'),
                    $sm->get('ViewRenderer'),
                    $sm->get(VehicleNameFormatter::class)
                );
            },
            HostManager::class => function($sm) {
                $config = $sm->get('Config');
                return new HostManager($config['hosts']);
            },
            Service\UsersService::class => function($sm) {
                $config = $sm->get('Config');
                return new Service\UsersService(
                    $config['users'],
                    $config['hosts'],
                    $sm->get('MvcTranslator'),
                    $sm->get(MailTransport::class),
                    $sm->get(Service\SpecificationsService::class),
                    $sm->get(Image\Storage::class));
            },
            Zend_Db_Adapter_Abstract::class => function($sm) {
                $config = $sm->get('Config');
                $resource = new Zend_Application_Resource_Db($config['db']);
                return $resource->init();
            },
            Service\TelegramService::class => function($sm) {
                $config = $sm->get('Config');
                return new Service\TelegramService(
                    $config['telegram'],
                    $sm->get('HttpRouter'),
                    $sm->get(HostManager::class),
                    $sm
                );
            },
            'translator' => \Zend\Mvc\I18n\TranslatorFactory::class,
            LanguagePicker::class => function($sm) {
                $request = $sm->get('Request');
                $config = $sm->get('Config');

                return new LanguagePicker($request, $config['hosts']);
            },
            MainMenu::class => function($sm) {

                $config = $sm->get('Config');

                return new MainMenu(
                    $sm->get('HttpRouter'),
                    $sm->get(Language::class),
                    $sm->get('longCache'),
                    $config['hosts'],
                    $sm->get('MvcTranslator'),
                    $sm->get(LanguagePicker::class),
                    $sm->get(Model\Message::class)
                );
            },
            Language::class => function($sm) {

                $request = $sm->get('Request');

                return new Language($request);
            },
            Acl::class => Permissions\AclFactory::class,
            ExternalLoginServiceFactory::class => function($sm) {
                $config = $sm->get('Config');
                return new ExternalLoginServiceFactory($config['externalloginservice']);
            },
            FileSize::class => function($sm) {
                return new FileSize();
            },
            Picture::class => function($sm) {
                return new Picture([
                    'imageStorage' => $sm->get(Image\Storage::class)
                ]);
            },
            Service\SpecificationsService::class => function($sm) {
                return new Service\SpecificationsService($sm->get('MvcTranslator'));
            },
            Zend_Cache_Manager::class => function($sm) {
                $config = $sm->get('Config');
                $resource = new Zend_Application_Resource_Cachemanager($config['cachemanager']);
                return $resource->init();
            },
        ],
        'aliases' => [
            'ZF\OAuth2\Provider\UserId' => Provider\UserId\OAuth2UserIdProvider::class
        ],
        'abstract_factories' => [
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            //'Zend\Form\FormAbstractServiceFactory',
        ],
        /*'services' => [),
        'factories' => [),
        'initializators' => [),
        'delegators' => [),
        'shared' => [)*/

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
            Validator\User\EmailExists::class => InvokableFactory::class,
            Validator\User\EmailNotExists::class => InvokableFactory::class,
            Validator\User\Login::class => InvokableFactory::class,
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
