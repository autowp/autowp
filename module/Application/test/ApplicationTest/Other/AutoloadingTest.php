<?php

namespace ApplicationTest\Other;

/**
 * @group Autowp_Autoloading
 */
class AutoloadingTest extends \PHPUnit_Framework_TestCase
{
    public function testProjectIsAutoloads()
    {
        $form = new \Zend\Form\Form();

        $this->assertTrue($form instanceof \Zend\Form\Form);
    }
}