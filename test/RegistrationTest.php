<?php

namespace AutowpTest;

use Users_Row;

use Zend_Controller_Front;

/**
 * @group Autowp_Registration
 */
class RegistrationTest extends \PHPUnit_Framework_TestCase
{
    public function testRegistration()
    {
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');

        $usersService = $bootstrap->getResource('users');
        $user = $usersService->addUser(array(
            'email'    => 'reg-test@autowp.ru',
            'password' => '123567894',
            'name'     => "TestRegistrationUser",
            'ip'       => '127.0.0.1'
        ), 'en');

        $this->assertInstanceOf(Users_Row::class, $user);
    }
}
