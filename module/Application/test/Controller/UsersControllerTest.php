<?php

namespace ApplicationTest\Controller;

use Application\Controller\UsersController;

use Application\Test\AbstractHttpControllerTestCase;

class UsersControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('https://www.autowp.ru/users/user1/comments', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user/comments');
    }
}
