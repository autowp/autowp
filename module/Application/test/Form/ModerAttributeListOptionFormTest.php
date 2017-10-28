<?php

namespace ApplicationTest\Form;

use Application\Test\AbstractHttpControllerTestCase;

class ModerAttributeListOptionFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testForm()
    {
        $form = new \Application\Form\Moder\AttributeListOption(null);

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\AttributeListOption::class, $form);
    }
}
