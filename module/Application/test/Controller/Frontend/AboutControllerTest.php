<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\AboutController;

class AboutControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/about', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AboutController::class);
        $this->assertMatchedRouteName('about');
        $this->assertActionName('index');
    }
}
