<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\LoginController;

class LoginControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testLoginByEmail()
    {
        $this->dispatch('https://www.autowp.ru/login', Request::METHOD_POST, [
            'login'    => 'test@example.com',
            'password' => '123456'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('login');
        $this->assertActionName('index');

        $this->assertQuery('.alert-success');
    }

    public function testLoginByLogin()
    {
        $this->dispatch('https://www.autowp.ru/login', Request::METHOD_POST, [
            'login'    => 'test',
            'password' => '123456'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('login');
        $this->assertActionName('index');

        $this->assertQuery('.alert-success');
    }
}
