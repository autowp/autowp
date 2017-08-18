<?php

namespace ApplicationTest\Model;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Model\CarOfDay;

class CarOfDayTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testCarOfDay()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(CarOfDay::class);
        $result = $model->getCarOfDayCadidate();

        $this->assertNotEmpty($result);
    }
}
