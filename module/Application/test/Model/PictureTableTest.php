<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;

use Application\Test\AbstractHttpControllerTestCase;
use Application\Model\DbTable;

class PictureTableTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testPattern()
    {
        $table = new DbTable\Picture();
        $row = $table->fetchRow([]);

        $pattern = $table->getFileNamePattern($row);

        $this->assertNotEmpty($pattern);
    }
}
