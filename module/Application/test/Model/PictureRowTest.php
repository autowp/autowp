<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Model\DbTable\Picture;

class PictureRowTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testPattern()
    {
        $table = new Picture();
        $row = $table->fetchRow([]);

        $pattern = $row->getFileNamePattern();

        $this->assertNotEmpty($pattern);
    }
}
