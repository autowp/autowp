<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\UserController;
use Application\Test\AbstractHttpControllerTestCase;
use ApplicationTest\Data;
use Laminas\Http\Header\Location;
use Laminas\Http\Request;
use Laminas\Http\Response;

use function count;
use function explode;
use function microtime;

class UserControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testDelete(): void
    {
        $email    = 'test' . microtime(true) . '@example.com';
        $password = 'password';

        $this->dispatch('https://www.autowp.ru/api/user', Request::METHOD_POST, [
            'email'            => $email,
            'name'             => 'Test user',
            'password'         => $password,
            'password_confirm' => $password,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/post');
        $this->assertActionName('post');

        // get id
        /** @var Response $response */
        $response = $this->getResponse();
        /** @var Location $location */
        $location = $response->getHeaders()->get('Location');
        $uri      = $location->uri();
        $parts    = explode('/', $uri->getPath());
        $userId   = $parts[count($parts) - 1];

        // delete user
        $this->reset();
        /** @var Request $request */
        $request = $this->getRequest();
        $request->getHeaders()->addHeader(Data::getAdminAuthHeader());
        $this->dispatch(
            'https://www.autowp.ru/api/user/' . $userId,
            Request::METHOD_PUT,
            [
                'deleted' => 1,
            ]
        );

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/user/put');
        $this->assertActionName('put');
    }

    public function testOnline(): void
    {
        $this->dispatch('https://www.autowp.ru/api/user/online', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(UserController::class);
        $this->assertMatchedRouteName('api/user/online');
        $this->assertActionName('online');
    }
}
