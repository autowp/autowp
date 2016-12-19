<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;

class ServicesTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/_files/application.config.php';

    /**
     * @dataProvider servicesProvider
     */
    public function testServiceRegistered($serviceName)
    {
        $services = $this->getApplicationServiceLocator();

        $service = $services->get($serviceName);

        $this->assertInstanceOf($serviceName, $service);
    }

    public static function servicesProvider()
    {
        return [
            [\Application\Model\Message::class],
            [\Application\VehicleNameFormatter::class],
            [\Application\PictureNameFormatter::class],
            [\Autowp\Image\Storage::class],
            [\Application\HostManager::class],
            [\Application\Service\UsersService::class],
            [\Zend_Db_Adapter_Abstract::class],
            [\Application\Service\TelegramService::class],
            [\Application\LanguagePicker::class],
            [\Application\MainMenu::class],
            [\Application\Language::class],
            [\Autowp\TextStorage\Service::class],
            [\Zend\Permissions\Acl\Acl::class],
            [\Autowp\ExternalLoginService\Factory::class],
            [\Application\FileSize::class],
            [\Application\Service\SpecificationsService::class],
            [\Zend_Cache_Manager::class],
        ];
    }
}
