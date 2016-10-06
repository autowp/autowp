<?php

namespace ApplicationTest\Other;

use Application\Model\DbTable\User\Row as UserRow;

use Zend\Test\PHPUnit\Controller\AbstractControllerTestCase;

/**
 * @group Autowp_Registration
 */
class RegistrationTest extends AbstractControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    /*public function testRegistration()
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $usersService = $serviceManager->get(\Application\Service\UsersService::class);

        $user = $usersService->addUser([
            'email'    => 'reg-test@autowp.ru',
            'password' => '123567894',
            'name'     => "TestRegistrationUser",
            'ip'       => '127.0.0.1'
        ], 'en');

        $this->assertInstanceOf(UserRow::class, $user);
    }*/
}
