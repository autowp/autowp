<?php

namespace ApplicationTest\Other;

use Application\Service\UsersService;
use Application\Test\AbstractHttpControllerTestCase;

class RegistrationTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    public function testRegistration(): void
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $usersService   = $serviceManager->get(UsersService::class);

        $user = $usersService->addUser([
            'email'    => 'reg-test@autowp.ru',
            'password' => '123567894',
            'name'     => "TestRegistrationUser",
            'ip'       => '127.0.0.1',
        ], 'en');

        $this->assertNotEmpty($user['id']);
    }
}
