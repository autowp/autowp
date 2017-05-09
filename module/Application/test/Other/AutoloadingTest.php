<?php

namespace ApplicationTest\Other;

class AutoloadingTest extends \PHPUnit\Framework\TestCase
{
    public function testProjectIsAutoloads()
    {
        $form = new \Zend\Form\Form();

        $this->assertTrue($form instanceof \Zend\Form\Form);
    }
}
