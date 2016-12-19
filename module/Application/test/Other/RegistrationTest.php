<?php

namespace ApplicationTest\Other;

use Autowp\User\Model\DbTable\User\Row as UserRow;

use Application\Test\AbstractHttpControllerTestCase;

class RegistrationTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

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

        $this->assertInstanceOf(UserRow::class, $user);
    }
}
