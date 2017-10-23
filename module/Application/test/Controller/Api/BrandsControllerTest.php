<?php

namespace ApplicationTest\Controller\Api;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\BrandsController;

class BrandsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testBrandsIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/brands', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(BrandsController::class);
        $this->assertMatchedRouteName('api/brands/get');
        $this->assertActionName('index');
    }
}
