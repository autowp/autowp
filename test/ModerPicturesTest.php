<?php

namespace AutowpTest;

use Application_Form_Moder_Picture_Accept;

use Zend_Controller_Front;

/**
 * @group Autowp_Registration
 */
class ModerFormsTest extends \PHPUnit_Framework_TestCase
{
    public function testAcceptForm()
    {
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');

        $form = new Application_Form_Moder_Picture_Accept();

        $this->assertInstanceOf(Application_Form_Moder_Picture_Accept::class, $form);
    }
}
