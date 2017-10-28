<?php

namespace ApplicationTest\Api\Controller;

use Zend\Http\Request;

use Application\Controller\Api\LoginController;
use Application\Controller\Api\RestorePasswordController;
use Application\Controller\Api\UserController;
use Application\Test\AbstractHttpControllerTestCase;

class RestorePasswordControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function createUser(string $email, string $password, string $name): int
    {
        $this->reset();

        $this->dispatch('https://www.autowp.ru/api/user', Request::METHOD_POST, [
            'email'            => $email,
            'name'             => $name,
            'password'         => $password,
            'password_confirm' => $password
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/post');
        $this->assertActionName('post');

        // get id
        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $userId = $parts[count($parts) - 1];

        return $userId;
    }

    private function activateUser()
    {
        $mailTransport = $this->getApplicationServiceLocator()->get(\Zend\Mail\Transport\TransportInterface::class);
        $message = $mailTransport->getLastMessage();

        preg_match('|http://en.localhost/ng/account/emailcheck/([0-9a-f]+)|u', $message->getBody(), $match);

        $this->reset();
        $this->dispatch('http://en.localhost/api/user/emailcheck', Request::METHOD_POST, [
            'code' => $match[1]
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/emailcheck');
        $this->assertActionName('emailcheck');
    }

    public function testRestorePassword()
    {
        $email = 'test'.microtime(true).'@example.com';
        $password = 'password';
        $newPassword = 'password2';
        $name = 'User, who restore password';

        $this->createUser($email, $password, $name);
        $this->activateUser();

        // request email message
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/restore-password/request', Request::METHOD_POST, [
            'email' => $email
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('api/restore-password/request/post');
        $this->assertActionName('request');

        // parse message for url with token
        $mailTransport = $this->getApplicationServiceLocator()->get(\Zend\Mail\Transport\TransportInterface::class);
        $message = $mailTransport->getLastMessage();

        preg_match('|https?://en.localhost/ng/restore-password/new\?code=([0-9a-f]+)|u', $message->getBody(), $match);
        $token = $match[1];

        // check token availability
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/restore-password/new', Request::METHOD_GET, [
            'code' => $token,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('api/restore-password/new/get');
        $this->assertActionName('new-get');

        // change password with token
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/restore-password/new', Request::METHOD_POST, [
            'code'             => $token,
            'password'         => $newPassword,
            'password_confirm' => $newPassword
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('api/restore-password/new/post');
        $this->assertActionName('new-post');

        // check new password
        // login
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => $email,
            'password' => $newPassword
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');
    }
}
