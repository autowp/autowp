<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Application\Test\AbstractHttpControllerTestCase;

class ModerCategoryLanguageFormTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $form = new \Application\Form\Moder\CategoryLanguage();

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\CategoryLanguage::class, $form);
    }
}
