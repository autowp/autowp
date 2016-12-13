<?php

namespace ApplicationTest\Other;

class AutoloadingTest extends \PHPUnit_Framework_TestCase
{
    public function testProjectIsAutoloads()
    {
        $form = new \Zend\Form\Form();

        $this->assertTrue($form instanceof \Zend\Form\Form);
    }
}
