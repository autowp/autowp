<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Model\CarOfDay;
use Application\Model\DbTable\Vehicle\Row;

class CarOfDayTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testCarOfDay()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(CarOfDay::class);
        $result = $model->getCarOfDayCadidate();

        if ($result) {
            $this->assertInstanceOf(Row::class, $result);
        } else {
            $this->assertFalse($result);
        }
    }
}
