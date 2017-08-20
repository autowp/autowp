<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Request;

use Autowp\User\Model\User;

use Application\Controller\AccountController;
use Application\Controller\LoginController;
use Application\Controller\RegistrationController;
use Application\Controller\RestorePasswordController;
use Application\Test\AbstractHttpControllerTestCase;

class RestorePasswordControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function createUser(string $email, string $password, string $name): int
    {
        $this->reset();
        $this->dispatch('https://www.autowp.ru/registration', Request::METHOD_POST, [
            'email'            => $email,
            'name'             => $name,
            'password'         => $password,
            'password_confirm' => $password
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RegistrationController::class);
        $this->assertMatchedRouteName('registration');
        $this->assertActionName('index');

        // get id
        $userModel = $this->getApplicationServiceLocator()->get(User::class);
        $table = $userModel->getTable();
        $userRow = $table->selectWith(
            $table->getSql()->select()
                ->order('id desc')
                ->limit(1)
        )->current();

        return $userRow['id'];
    }

    private function activateUser()
    {
        $mailTransport = $this->getApplicationServiceLocator()->get(\Zend\Mail\Transport\TransportInterface::class);
        $message = $mailTransport->getLastMessage();

        preg_match('|http://en.localhost/account/emailcheck/[0-9a-f]+|u', $message->getBody(), $match);

        $this->reset();
        $this->dispatch($match[0]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/emailcheck');
        $this->assertActionName('emailcheck');
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/restorepassword', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('restorepassword');
        $this->assertActionName('index');

        $this->assertQuery("h1");
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
        $this->dispatch('https://www.autowp.ru/restorepassword', Request::METHOD_POST, [
            'email' => $email
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('restorepassword');
        $this->assertActionName('index');

        // parse message for url with token
        $mailTransport = $this->getApplicationServiceLocator()->get(\Zend\Mail\Transport\TransportInterface::class);
        $message = $mailTransport->getLastMessage();

        preg_match('|https?://en.localhost/restorepassword/new/[0-9a-f]+|u', $message->getBody(), $match);
        $url = $match[0];

        // change password with token
        $this->reset();
        $this->dispatch($url, Request::METHOD_POST, [
            'password'         => $newPassword,
            'password_confirm' => $newPassword
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(RestorePasswordController::class);
        $this->assertMatchedRouteName('restorepassword/new');
        $this->assertActionName('new');

        // check new password
        // login
        $this->reset();
        $this->dispatch('https://www.autowp.ru/login', Request::METHOD_POST, [
            'login'    => $email,
            'password' => $newPassword
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('login');
        $this->assertActionName('index');

        $this->assertQuery('.alert-success');
    }
}
