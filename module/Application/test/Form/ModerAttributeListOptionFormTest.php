<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class ModerAttributeListOptionFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $table = new \Application\Model\DbTable\Attr\ListOption();
        $row = $table->fetchRow([]);

        $form = new \Application\Form\Moder\AttributeListOption(null, [
            'attribute' => $row
        ]);

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\AttributeListOption::class, $form);
    }
}
