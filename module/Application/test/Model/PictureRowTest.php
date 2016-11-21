<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Application\Model\DbTable\Picture;

class PictureRowTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testPattern()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = new Picture();
        $row = $table->fetchRow([]);

        $pattern = $row->getFileNamePattern();

        $this->assertNotEmpty($pattern);
    }
}
