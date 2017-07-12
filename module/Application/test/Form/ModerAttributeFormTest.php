<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class ModerAttributeFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testForm()
    {
        $form = new \Application\Form\Moder\Attribute();

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\Attribute::class, $form);
    }
}
