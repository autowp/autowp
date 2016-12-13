<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Model\DbTable\Brand;

class BrandTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testInsert()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = new Brand();
        $row = $table->createRow([
            'name'    => 'test-brand' . rand(),
            'type_id' => 1
        ]);

        $row->save();

        $this->assertNotEmpty($row->id);
    }
}
