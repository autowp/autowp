<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\ForumsController;
use Zend\Http\Header\Cookie;

class ForumsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/forums', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums');
        $this->assertActionName('index');
    }

    public function testTopic()
    {
        $this->dispatch('https://www.autowp.ru/forums/topic/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/topic');
        $this->assertActionName('topic');
    }

    public function testNewIsForbidden()
    {
        $this->dispatch('https://www.autowp.ru/forums/new/theme_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(403);
    }

    public function testNew()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/forums/new/theme_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ForumsController::class);
        $this->assertMatchedRouteName('forums/new');
        $this->assertActionName('new');
    }
}
