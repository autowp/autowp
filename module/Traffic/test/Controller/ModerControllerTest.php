<?php

namespace AutowpTest\Traffic\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Autowp\Traffic\Controller\ModerController;
use Zend\Http\Request;
use Zend\Http\Header\Cookie;

class ModerControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testIndexForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic');
        $this->assertActionName('forbidden');
    }

    public function testIndex()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/traffic', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic');
        $this->assertActionName('index');
    }

    public function testHostByAddrForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic/host-by-addr', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/host-by-addr');
        $this->assertActionName('forbidden');
    }

    public function testHostByAddr()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/traffic/host-by-addr', Request::METHOD_GET, [
            'ip' => '127.0.0.1'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/host-by-addr');
        $this->assertActionName('host-by-addr');
    }

    public function testWhitelistForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist');
        $this->assertActionName('forbidden');
    }

    public function testWhitelist()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist');
        $this->assertActionName('whitelist');
    }

    public function testWhitelistRemoveForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist-remove', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist-remove');
        $this->assertActionName('forbidden');
    }

    public function testWhitelistAddForbidden()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist-add', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist-add');
        $this->assertActionName('forbidden');
    }

    public function testWhitelistAddRemove()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist-add', Request::METHOD_POST, [
            'ip' => '127.0.0.1'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist-add');
        $this->assertActionName('whitelist-add');

        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/traffic/whitelist-remove', Request::METHOD_POST, [
            'ip' => '127.0.0.1'
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertControllerName(ModerController::class);
        $this->assertMatchedRouteName('moder/traffic/whitelist-remove');
        $this->assertActionName('whitelist-remove');
    }
}
