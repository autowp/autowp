<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Request;

use Application\Controller\Api\MapController;
use Application\Test\AbstractHttpControllerTestCase;

class MapControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testData()
    {
        $this->dispatch('https://www.autowp.ru/api/map/data', Request::METHOD_GET, [
            'bounds' => '-90,-180,90,180'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(MapController::class);
        $this->assertMatchedRouteName('api/map/data');
    }
}
