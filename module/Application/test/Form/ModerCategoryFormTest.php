<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class ModerCategoryFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $form = new \Application\Form\Moder\Category();

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\Category::class, $form);
    }
}
