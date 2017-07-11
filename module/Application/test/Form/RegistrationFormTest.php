<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class RegistrationFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $form = $serviceManager->get('RegistrationForm');

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(Form::class, $form);
    }
}
