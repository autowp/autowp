<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;

use Application\Model\Picture;
use Application\Test\AbstractHttpControllerTestCase;

class PictureTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testPattern()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = $serviceManager->get(Picture::class);

        $row = $table->getRow([]);

        $pattern = $table->getFileNamePattern($row);

        $this->assertNotEmpty($pattern);
    }
}
