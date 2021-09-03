<?php

namespace ApplicationTest\Controller\Api;

use Application\Controller\Api\UserController;
use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Http\Request;

class UserControllerTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

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
