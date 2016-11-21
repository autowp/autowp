<?php

namespace ApplicationTest\Form;

use Zend\Form\Form;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class ModerCategoryLanguageFormTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testForm()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $form = new \Application\Form\Moder\CategoryLanguage();

        $form->setData([]);
        $form->isValid();

        $this->assertInstanceOf(\Application\Form\Moder\CategoryLanguage::class, $form);
    }
}
