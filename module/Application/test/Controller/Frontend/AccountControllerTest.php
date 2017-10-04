<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Autowp\User\Model\User;

use Application\Controller\AccountController;
use Application\Controller\Api\LoginController;
use Application\Controller\Api\UserController;
use Application\Controller\UsersController;
use Application\Test\AbstractHttpControllerTestCase;

class AccountControllerTest extends AbstractHttpControllerTestCase
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

        preg_match('|http://en.localhost/account/emailcheck/[0-9a-f]+|u', $message->getBody(), $match);

        $this->reset();
        $this->dispatch($match[0]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/emailcheck');
        $this->assertActionName('emailcheck');
    }

    private function getUser(int $userId)
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/user/' . $userId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/item');
        $this->assertActionName('item');

        return Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
    }

    public function testEmail()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/email', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/email');
        $this->assertActionName('email');
    }

    public function testAccess()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/access', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/access');
        $this->assertActionName('access');
    }

    public function testAccounts()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/accounts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/accounts');
        $this->assertActionName('accounts');
    }

    public function testSpecsConflicts()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/specs-conflicts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/specs-conflicts');
        $this->assertActionName('specs-conflicts');
    }

    public function testProfileRename()
    {
        $email = 'test'.microtime(true).'@example.com';
        $password = 'password';
        $name1 = 'First name';
        $name2 = 'Second name';

        $userId = $this->createUser($email, $password, $name1);
        $this->activateUser();

        $user1 = $this->getUser($userId);
        $this->assertEquals($name1, $user1['name']);

        // login
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => $email,
            'password' => $password,
            'remember' => 1
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');

        $token = $this->getApplicationServiceLocator()->get(\Autowp\User\Model\UserRemember::class)
            ->getUserToken($userId);

        $this->assertNotEmpty($token);


        // rename
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/api/user/me', Request::METHOD_PUT, [
            'name' => $name2
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/put');
        $this->assertActionName('put');

        $user2 = $this->getUser($userId);
        $this->assertEquals($name2, $user2['name']);

        // request user page
        $this->reset();
        $this->dispatch('https://www.autowp.ru/users/user' . $userId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('users/user');
    }
}
