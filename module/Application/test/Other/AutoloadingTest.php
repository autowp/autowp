<?php

namespace ApplicationTest\Other;

use Laminas\Form\Form;
use PHPUnit\Framework\TestCase;

class AutoloadingTest extends TestCase
{
    public function testProjectIsAutoloads()
    {
        $form = new Form();

        $this->assertTrue($form instanceof Form);
    }
}
