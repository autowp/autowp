<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\LogController;
use Application\Test\AbstractHttpControllerTestCase;

class LogControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/log/index', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(LogController::class);
        $this->assertMatchedRouteName('log');
    }
}
