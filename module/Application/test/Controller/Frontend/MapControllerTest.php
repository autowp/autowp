<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Request;

use Application\Controller\MapController;
use Application\Test\AbstractHttpControllerTestCase;

class MapControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/map', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MapController::class);
        $this->assertMatchedRouteName('map');
    }

    public function testData()
    {
        $this->dispatch('https://www.autowp.ru/map/data', Request::METHOD_GET, [
            'bounds' => '-90,-180,90,180'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MapController::class);
        $this->assertMatchedRouteName('map/data');
    }
}
