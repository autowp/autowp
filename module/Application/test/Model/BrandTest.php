<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Model\DbTable\Brand;

class BrandTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testInsert()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = new Brand();
        $row = $table->createRow([
            'caption' => 'test-brand' . rand(),
            'type_id' => 1
        ]);

        $row->save();

        $this->assertNotEmpty($row->id);
    }
}
