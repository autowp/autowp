<?php

namespace ApplicationTest\Other;

use PHPUnit\Framework\TestCase;
use Zend\Form\Form;

class AutoloadingTest extends TestCase
{
    public function testProjectIsAutoloads()
    {
        $form = new Form();

        $this->assertTrue($form instanceof Form);
    }
}
