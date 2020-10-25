<?php

namespace AutowpTest\Traffic\Controller;

use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Autowp\Traffic\Controller\BanController;
use Laminas\Http\Request;

class BanControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testBanIpForbidden(): void
    {
        $this->dispatch('https://www.autowp.ru/ban/ban-ip/ip/127.0.0.1', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-ip');
        $this->assertActionName('forbidden');
    }

    public function testUnbanIpForbidden(): void
    {
        $this->dispatch('https://www.autowp.ru/ban/unban-ip/ip/127.0.0.1', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/unban-ip');
        $this->assertActionName('forbidden');
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testBanUnbanIp(): void
    {
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/ban/ban-ip/ip/127.0.0.1', Request::METHOD_POST, [
            'period' => '1',
            'reason' => 'test',
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-ip');
        $this->assertActionName('ban-ip');

        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/ban/unban-ip/ip/127.0.0.1', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/unban-ip');
        $this->assertActionName('unban-ip');
    }
}
