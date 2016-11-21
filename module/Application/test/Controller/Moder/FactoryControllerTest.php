<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\FactoryController;

class FactoryControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/factory', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(FactoryController::class);
        $this->assertMatchedRouteName('moder/factories');
        $this->assertActionName('forbidden');
    }

    public function testFactory()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/factory/factory/factory_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(FactoryController::class);
        $this->assertMatchedRouteName('moder/factories/params');
        $this->assertActionName('factory');
    }
}
