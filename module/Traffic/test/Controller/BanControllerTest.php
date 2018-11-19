<?php

namespace AutowpTest\Traffic\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Autowp\Traffic\Controller\BanController;
use Zend\Http\Request;
use Zend\Http\Header\Cookie;

class BanControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testBanIpForbidden()
    {
        $this->dispatch('https://www.autowp.ru/ban/ban-ip/ip/127.0.0.1', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-ip');
        $this->assertActionName('forbidden');
    }

    public function testUnbanIpForbidden()
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
    public function testBanUnbanIp()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/ban/ban-ip/ip/127.0.0.1', Request::METHOD_POST, [
            'period' => '1',
            'reason' => 'test'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-ip');
        $this->assertActionName('ban-ip');

        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/ban/unban-ip/ip/127.0.0.1', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/unban-ip');
        $this->assertActionName('unban-ip');
    }
}
