<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\MostsController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Request;

class MostsControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex(): void
    {
        $this->dispatch('https://www.autowp.ru/api/mosts/menu', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MostsController::class);
        $this->assertMatchedRouteName('api/mosts/menu/get');
        $this->assertActionName('get-menu');
    }

    public function testVehicleType(): void
    {
        $this->dispatch('https://www.autowp.ru/api/mosts/items', Request::METHOD_GET, [
            'rating_catname' => 'fastest',
            'type_catname'   => 'car',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MostsController::class);
        $this->assertMatchedRouteName('api/mosts/items/get');
        $this->assertActionName('get-items');
    }
}
