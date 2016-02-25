<?php

namespace AutowpTest;

use Project_Form;

/**
 * @group Autowp_Autoloading
 */
class AutoloadingTest extends \PHPUnit_Framework_TestCase
{

    public function testProjectIsAutoloads()
    {
        $form = new Project_Form();
        
        $this->assertTrue($form instanceof Project_Form);
    }
}