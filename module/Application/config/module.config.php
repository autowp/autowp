<?php

namespace Application;

use Autowp\ZFComponents\Resources;
use Casbin\Enforcer;
use Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use Laminas\I18n\Translator\Loader\PhpArray;
use Laminas\I18n\Translator\Resources as TranslatorResources;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\Mvc\I18n\TranslatorFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'controllers'        => [
        'factories' => [
            Controller\Frontend\YandexController::class => Controller\Frontend\Service\YandexControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'forbiddenAction'     => Controller\Plugin\ForbiddenAction::class,
            'inputFilterResponse' => Controller\Api\Plugin\InputFilterResponse::class,
            'inputResponse'       => Controller\Api\Plugin\InputResponse::class,
        ],
        'factories'  => [
            'car'         => Controller\Plugin\Service\CarFactory::class,
            'catalogue'   => Controller\Plugin\Service\CatalogueFactory::class,
            'fileSize'    => Controller\Plugin\Service\FileSizeFactory::class,
            'language'    => Controller\Plugin\Service\LanguageFactory::class,
            'log'         => Controller\Plugin\Service\LogFactory::class,
            'pic'         => Controller\Plugin\Service\PicFactory::class,
            'pictureVote' => Controller\Plugin\Service\PictureVoteFactory::class,
            'translate'   => Controller\Plugin\Service\TranslateFactory::class,
        ],
    ],
    'translator'         => [
        'locale'                    => 'ru',
        'fallbackLocale'            => 'en',
        'translation_file_patterns' => [
            [
                'type'     => PhpArray::class,
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.php',
            ],
            [
                'type'     => PhpArray::class,
                'base_dir' => __DIR__ . '/../language/plural',
                'pattern'  => '%s.php',
            ],
            [
                'type'     => PhpArray::class,
                'base_dir' => TranslatorResources::getBasePath(),
                'pattern'  => TranslatorResources::getPatternForValidator(),
            ],
            [
                'type'     => PhpArray::class,
                'base_dir' => TranslatorResources::getBasePath(),
                'pattern'  => TranslatorResources::getPatternForCaptcha(),
            ],
            [
                'type'     => PhpArray::class,
                'base_dir' => Resources::getBasePath(),
                'pattern'  => Resources::getPatternForViewHelpers(),
            ],
        ],
    ],
    'service_manager'    => [
        'factories'          => [
            Comments::class                      => Service\CommentsFactory::class,
            DuplicateFinder::class               => Service\DuplicateFinderFactory::class,
            Enforcer::class                      => Permissions\CasbinFactory::class,
            FileSize::class                      => InvokableFactory::class,
            HostManager::class                   => Service\HostManagerFactory::class,
            Language::class                      => Service\LanguageFactory::class,
            Model\Brand::class                   => Model\BrandFactory::class,
            Model\CarOfDay::class                => Model\Service\CarOfDayFactory::class,
            Model\Catalogue::class               => Model\Service\CatalogueFactory::class,
            Model\Categories::class              => Model\Service\CategoriesFactory::class,
            Model\Contact::class                 => Model\ContactFactory::class,
            Model\Item::class                    => Model\ItemFactory::class,
            Model\ItemAlias::class               => Model\ItemAliasFactory::class,
            Model\ItemParent::class              => Model\ItemParentFactory::class,
            Model\Log::class                     => Model\Service\LogFactory::class,
            Model\Perspective::class             => Model\PerspectiveFactory::class,
            Model\Picture::class                 => Model\PictureFactory::class,
            Model\PictureItem::class             => Model\PictureItemFactory::class,
            Model\PictureModerVote::class        => Model\PictureModerVoteFactory::class,
            Model\PictureView::class             => Model\PictureViewFactory::class,
            Model\PictureVote::class             => Model\Service\PictureVoteFactory::class,
            Model\Twins::class                   => Model\TwinsFactory::class,
            Model\UserPicture::class             => Model\Service\UserPictureFactory::class,
            Model\UserAccount::class             => Model\UserAccountFactory::class,
            Model\UserItemSubscribe::class       => Model\UserItemSubscribeFactory::class,
            Model\VehicleType::class             => Model\VehicleTypeFactory::class,
            PictureNameFormatter::class          => Service\PictureNameFormatterFactory::class,
            Service\Mosts::class                 => Service\MostsFactory::class,
            Service\PictureService::class        => Service\PictureServiceFactory::class,
            Service\SpecificationsService::class => Service\SpecificationsServiceFactory::class,
            Service\TelegramService::class       => Service\TelegramServiceFactory::class,
            Service\UsersService::class          => Service\UsersServiceFactory::class,
            ItemNameFormatter::class             => Service\ItemNameFormatterFactory::class,
            'translator'                         => TranslatorFactory::class,
        ],
        'abstract_factories' => [
            StorageCacheAbstractServiceFactory::class,
        ],
    ],
    'telegram'           => [
        'accessToken' => '',
        'token'       => 'example',
        'webhook'     => 'http://localhost/api/telegram/webhook/token/example',
    ],
    'twitter'            => [
        'username'     => '',
        'oauthOptions' => [
            'consumerKey'    => '',
            'consumerSecret' => '',
        ],
        'token'        => [
            'oauth_token'        => '',
            'oauth_token_secret' => '',
        ],
    ],
    'facebook'           => [
        'app_id'            => '',
        'app_secret'        => '',
        'page_access_token' => '',
    ],
    'hosts'              => [
        'en'    => [
            'hostname' => 'en.localhost',
            'timezone' => 'Europe/London',
            'name'     => 'English',
            'flag'     => 'flag-icon flag-icon-gb',
            'aliases'  => [],
        ],
        'zh'    => [
            'hostname' => 'zh.localhost',
            'timezone' => 'Asia/Shanghai',
            'name'     => '中文 (beta)',
            'flag'     => 'flag-icon flag-icon-cn',
            'aliases'  => [],
        ],
        'ru'    => [
            'hostname' => 'ru.localhost',
            'timezone' => 'Europe/Moscow',
            'name'     => 'Русский',
            'flag'     => 'flag-icon flag-icon-ru',
            'aliases'  => [],
        ],
        'pt-br' => [
            'hostname' => 'br.localhost',
            'timezone' => 'Brazil/West',
            'name'     => 'Português brasileiro',
            'flag'     => 'flag-icon flag-icon-br',
            'aliases'  => [],
        ],
        'fr'    => [
            'hostname' => 'fr.localhost',
            'timezone' => 'Europe/Paris',
            'name'     => 'Français (beta)',
            'flag'     => 'flag-icon flag-icon-fr',
            'aliases'  => [],
        ],
        'be'    => [
            'hostname' => 'be.localhost',
            'timezone' => 'Europe/Minsk',
            'name'     => 'Беларуская',
            'flag'     => 'flag-icon flag-icon-by',
            'aliases'  => [],
        ],
        'uk'    => [
            'hostname' => 'uk.localhost',
            'timezone' => 'Europe/Kiev',
            'name'     => 'Українська (beta)',
            'flag'     => 'flag-icon flag-icon-ua',
            'aliases'  => [],
        ],
        'es'    => [
            'hostname' => 'es.localhost',
            'timezone' => 'Europe/Madrid',
            'name'     => 'Español (beta)',
            'flag'     => 'flag-icon flag-icon-es',
            'aliases'  => [],
        ],
    ],
    'content_languages'  => ['en', 'ru', 'uk', 'be', 'fr', 'it', 'zh', 'pt', 'de', 'es', 'jp'],

    /*'acl' => [
        'cache'         => 'long',
        'cacheLifetime' => 3600
    ],*/
    'textstorage'              => [
        'textTableName'     => 'textstorage_text',
        'revisionTableName' => 'textstorage_revision',
    ],
    'feedback'                 => [
        'from'     => 'no-reply@autowp.ru',
        'fromname' => 'Robot autowp.ru',
        'to'       => 'test@example.com',
        'subject'  => 'AutoWP Feedback',
    ],
    'validators'               => [
        'aliases'   => [
            'ItemCatnameNotExists' => Validator\Item\CatnameNotExists::class,
        ],
        'factories' => [
            Validator\Attr\AttributeId::class            => Validator\Attr\AttributeIdFactory::class,
            Validator\Attr\TypeId::class                 => Validator\Attr\TypeIdFactory::class,
            Validator\Attr\UnitId::class                 => Validator\Attr\UnitIdFactory::class,
            Validator\Item\CatnameNotExists::class       => Validator\Item\CatnameNotExistsFactory::class,
            Validator\ItemParent\CatnameNotExists::class => Validator\ItemParent\CatnameNotExistsFactory::class,
            Validator\User\EmailExists::class            => Validator\User\EmailExistsFactory::class,
            Validator\User\EmailNotExists::class         => Validator\User\EmailNotExistsFactory::class,
            Validator\User\Login::class                  => Validator\User\LoginFactory::class,
            Validator\DateString::class                  => InvokableFactory::class,
        ],
    ],
    'gulp-rev'                 => [
        'manifest' => __DIR__ . '/../../../public_html/dist/manifest.json',
        'prefix'   => '',
    ],
    'mosts_min_vehicles_count' => 200,
    'yandex'                   => [
        'secret' => '',
        'price'  => 1,
    ],
    'vk'                       => [
        'token'    => '',
        'owner_id' => '',
    ],
    'input_filters'            => [
        'factories'          => [
            InputFilter\AttrUserValueCollectionInputFilter::class
                => InputFilter\AttrUserValueCollectionInputFilterFactory::class,
        ],
        'abstract_factories' => [
            InputFilterAbstractServiceFactory::class,
        ],
    ],
    'users'                    => [
        'salt'      => 'users-salt',
        'emailSalt' => 'email-salt',
    ],
    'mail'                     => [
        'transport' => [
            'type' => 'in-memory',
        ],
    ],
    'recaptcha'                => [
        'publicKey'  => 'public',
        'privateKey' => 'private',
    ],
    'sentry'                   => [
        'dsn'         => '',
        'environment' => 'development',
        'release'     => '',
    ],
    'traffic'                  => [
        'url' => 'http://goautowp-serve-private:8080',
    ],
    'authSecret'               => 'example_secret',
    'fileStorage'              => [
        's3'          => [
            'region'                  => '',
            'version'                 => 'latest',
            'endpoint'                => 'http://minio:9000',
            'credentials'             => [
                'key'    => 'key',
                'secret' => 'secret',
            ],
            'use_path_style_endpoint' => true,
        ],
        'bucket'      => 'files',
        'srcOverride' => [],
    ],
    'captcha'                  => false,
];
