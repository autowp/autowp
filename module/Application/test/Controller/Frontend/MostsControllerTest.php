<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\MostsController;

class MostsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/mosts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MostsController::class);
        $this->assertMatchedRouteName('mosts');
        $this->assertActionName('index');
    }
}
