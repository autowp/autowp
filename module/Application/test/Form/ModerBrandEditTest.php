<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class ModerBrandEditTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $form = $serviceManager->get('ModerBrandEdit');

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\Brand\Edit::class, $form);
    }
}
