<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Zend\Json\Json;

use Autowp\User\Model\User;

use Application\Controller\AccountController;
use Application\Controller\Api\UserController;
use Application\Controller\RegistrationController;
use Application\Controller\UsersController;
use Application\Test\AbstractHttpControllerTestCase;
use Application\Controller\LoginController;

class AccountControllerTest extends AbstractHttpControllerTestCase
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

    public function testSendMessage()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/send-personal-message', Request::METHOD_POST, [
            'user_id' => 1,
            'message' => 'Test personal message'
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/send-personal-message');
        $this->assertActionName('send-personal-message');

        $json = Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);

        $this->assertTrue($json['ok']);
    }

    public function testGetInboxMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages');
        $this->assertActionName('personal-messages-inbox');
    }

    public function testGetSentMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/sent', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/sent');
        $this->assertActionName('personal-messages-sent');
    }

    public function testGetSystemMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/system', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/system');
        $this->assertActionName('personal-messages-system');
    }

    public function testGetUserMessages()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/pm/user1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/personal-messages/user');
        $this->assertActionName('personal-messages-user');
    }

    public function testProfile()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account');
        $this->assertActionName('profile');
    }

    public function testContacts()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/account/contacts', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/contacts');
        $this->assertActionName('contacts');
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
        $this->dispatch('https://www.autowp.ru/login', Request::METHOD_POST, [
            'login'    => $email,
            'password' => $password,
            'remember' => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('login');
        $this->assertActionName('index');

        $this->assertQuery('.alert-success');

        $token = $this->getApplicationServiceLocator()->get(\Autowp\User\Model\UserRemember::class)
            ->getUserToken($userId);

        $this->assertNotEmpty($token);


        // rename
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/account/profile/profile', Request::METHOD_POST, [
            'name' => $name2
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(AccountController::class);
        $this->assertMatchedRouteName('account/profile');
        $this->assertActionName('profile');


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
