<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\LoginController;

class LoginControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

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
