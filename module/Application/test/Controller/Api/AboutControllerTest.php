<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\AboutController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Request;

class AboutControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/about', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AboutController::class);
        $this->assertMatchedRouteName('api/about');
        $this->assertActionName('index');
    }
}
