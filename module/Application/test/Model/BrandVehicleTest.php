<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Model\BrandVehicle;

class BrandVehicleTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

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
