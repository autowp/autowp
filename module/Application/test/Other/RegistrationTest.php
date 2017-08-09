<?php

namespace ApplicationTest\Other;

use Application\Test\AbstractHttpControllerTestCase;

class RegistrationTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testRegistration()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $usersService = $serviceManager->get(\Application\Service\UsersService::class);

        $user = $usersService->addUser([
            'email'    => 'reg-test@autowp.ru',
            'password' => '123567894',
            'name'     => "TestRegistrationUser",
            'ip'       => '127.0.0.1'
        ], 'en');

        $this->assertInstanceOf(\Zend_Db_Table_Row_Abstract::class, $user);
    }
}
