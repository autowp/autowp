<?php

namespace AutowpTest\Traffic\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Autowp\Traffic\Controller\BanController;
use Zend\Http\Request;
use Zend\Http\Header\Cookie;

class BanControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

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

    public function testBanUserForbidden()
    {
        $this->dispatch('https://www.autowp.ru/ban/ban-user/user_id/1', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-user');
        $this->assertActionName('forbidden');
    }

    public function testUnbanUserForbidden()
    {
        $this->dispatch('https://www.autowp.ru/ban/unban-user/user_id/1', Request::METHOD_POST);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/unban-user');
        $this->assertActionName('forbidden');
    }

    public function testBanUnbanUser()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/ban/ban-user/user_id/1', Request::METHOD_POST, [
            'period' => '1',
            'reason' => 'test'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/ban-user');
        $this->assertActionName('ban-user');

        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/ban/unban-user/user_id/1', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(BanController::class);
        $this->assertMatchedRouteName('ban/unban-user');
        $this->assertActionName('unban-user');
    }
}
