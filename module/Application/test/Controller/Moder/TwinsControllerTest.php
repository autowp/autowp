<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Http\Request;
use Zend\Http\Header\Cookie;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\TwinsController;

class TwinsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/twins', Request::METHOD_GET);

        $this->assertResponseStatusCode(404);
        $this->assertModuleName('application');
        $this->assertControllerName(TwinsController::class);
        $this->assertMatchedRouteName('moder/twins/params');
        $this->assertActionName('not-found');
    }

    public function testGroup()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/moder/twins/twins-group/twins_group_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(TwinsController::class);
        $this->assertMatchedRouteName('moder/twins/params');
        $this->assertActionName('twins-group');
    }
}
