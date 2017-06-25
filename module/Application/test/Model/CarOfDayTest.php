<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Model\CarOfDay;

class CarOfDayTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testCarOfDay()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(CarOfDay::class);
        $result = $model->getCarOfDayCadidate();

        if ($result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertNotEmpty($result['id']);
        } else {
            $this->assertFalse($result);
        }
    }
}
