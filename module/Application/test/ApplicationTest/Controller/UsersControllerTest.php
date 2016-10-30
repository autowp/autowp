<?php

namespace ApplicationTest\Controller;

use Application\Controller\UsersController;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class UsersControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('https://www.autowp.ru/users/user1/comments', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user/comments');
    }
}
