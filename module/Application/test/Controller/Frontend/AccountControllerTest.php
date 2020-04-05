<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Api\AttrController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Exception;
use Laminas\Http\Request;

class AccountControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    /*private function createUser(string $email, string $password, string $name): int
    {
        $this->reset();

        $this->dispatch('https://www.autowp.ru/api/user', Request::METHOD_POST, [
            'email'            => $email,
            'name'             => $name,
            'password'         => $password,
            'password_confirm' => $password,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/post');
        $this->assertActionName('post');

        // get id
        $headers = $this->getResponse()->getHeaders();
        $uri     = $headers->get('Location')->uri();
        $parts   = explode('/', $uri->getPath());
        return (int) $parts[count($parts) - 1];
    }

    private function activateUser()
    {
        $mailTransport = $this->getApplicationServiceLocator()->get(TransportInterface::class);
        $message       = $mailTransport->getLastMessage();

        preg_match('|https://en.localhost/account/emailcheck/([0-9a-f]+)|u', $message->getBody(), $match);

        $this->reset();
        $this->dispatch('http://en.localhost/api/user/emailcheck', Request::METHOD_POST, [
            'code' => $match[1],
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/emailcheck');
        $this->assertActionName('emailcheck');
    }*/

    /**
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    /*private function getUser(int $userId): array
    {
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/user/' . $userId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/item');
        $this->assertActionName('item');

        return Json::decode($this->getResponse()->getContent(), Json::TYPE_ARRAY);
    }*/

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function testSpecsConflicts()
    {
        $this->getRequest()->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch('https://www.autowp.ru/api/attr/conflict?filter=0', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(AttrController::class);
        $this->assertMatchedRouteName('api/attr/conflict/get');
        $this->assertActionName('conflict-index');
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    /*public function testProfileRename()
    {
        $email    = 'test' . microtime(true) . '@example.com';
        $password = 'password';
        $name1    = 'First name';
        $name2    = 'Second name';

        $userId = $this->createUser($email, $password, $name1);
        $this->activateUser();

        $user1 = $this->getUser($userId);
        $this->assertEquals($name1, $user1['name']);

        // login
        $this->reset();
        $this->dispatch('https://www.autowp.ru/api/login', Request::METHOD_POST, [
            'login'    => $email,
            'password' => $password,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(LoginController::class);
        $this->assertMatchedRouteName('api/login/login');
        $this->assertActionName('login');

        $token = $this->getApplicationServiceLocator()->get(UserRemember::class)
            ->getUserToken($userId);

        $this->assertNotEmpty($token);

        // rename
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=' . $token));
        $this->dispatch('https://www.autowp.ru/api/user/me', Request::METHOD_PUT, [
            'name' => $name2,
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
        $this->dispatch('https://www.autowp.ru/api/user/' . $userId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/item');
    }*/
}
