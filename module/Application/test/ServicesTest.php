<?php

namespace ApplicationTest\Controller;

use Application\FileSize;
use Application\HostManager;
use Application\ItemNameFormatter;
use Application\Language;
use Application\LanguagePicker;
use Application\MainMenu;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;
use Application\Service\UsersService;
use Application\Test\AbstractHttpControllerTestCase;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service;
use Laminas\Permissions\Acl\Acl;

class ServicesTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    /**
     * @dataProvider servicesProvider
     */
    public function testServiceRegistered(string $serviceName)
    {
        $services = $this->getApplicationServiceLocator();

        $service = $services->get($serviceName);

        $this->assertInstanceOf($serviceName, $service);
    }

    public static function servicesProvider(): array
    {
        return [
            [MessageService::class],
            [ItemNameFormatter::class],
            [PictureNameFormatter::class],
            [Storage::class],
            [HostManager::class],
            [UsersService::class],
            [TelegramService::class],
            [LanguagePicker::class],
            [MainMenu::class],
            [Language::class],
            [Service::class],
            [Acl::class],
            [FileSize::class],
            [SpecificationsService::class],
        ];
    }
}
