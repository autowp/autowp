<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Model\BrandVehicle;

class BrandVehicleTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testInsert()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $model = $serviceManager->get(BrandVehicle::class);
        $result = $model->create(2, 1);

        $this->assertTrue($result);

        $result = $model->delete(2, 1);

        $this->assertTrue($result);
    }
}
